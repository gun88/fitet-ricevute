<?php
if(PHP_SAPI!=='cli')exit;
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';require __DIR__.'/.runtime/wordpress/wp-load.php';
$checks=0;function wf_check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function wf_reject($action,$message){$failed=false;try{$action();}catch(RuntimeException $error){$failed=true;}wf_check($failed,$message);}
$repo=new FR_Data_Repository();$wf=new FR_Refund_Workflow($repo);$prefix=$wpdb->prefix.'fitet_ricevute_';$config=FR_Settings::all();$config['society_code']='718';update_option(FR_Settings::OPTION,$config);
$ids=array_map('intval',array_column($repo->listing('iscrizioni')['items'],'id'));$selected=array_slice($ids,0,2);$user=90001;$other=90002;
$prices=$repo->listing('gare')['items'];$original_paid=$wpdb->get_results("SELECT * FROM {$prefix}rimborsi",ARRAY_A);
try{
 $repo->set_reimbursed($ids,false,1);$repo->set_manual_price((int)$prices[0]['id'],null,1);
 wf_check($wf->cart($user)['items']===[],'new user cart starts empty');
 $cart_lock='fr_cart_lock_'.hash('sha256',$user.':718');add_option($cart_lock,time(),'',false);wf_reject(fn()=>$wf->cart_change($user,$selected,true),'concurrent cart additions serialized');delete_option($cart_lock);
 $wf->cart_change($user,$selected,true);wf_check(count((new FR_Refund_Workflow($repo))->cart($user)['items'])===2,'cart persists across repository instances');
 wf_check($wf->cart($other)['items']===[],'cart isolated by user');
 $wf->cart_change($other,$selected,false);wf_check(count($wf->cart($user)['items'])===2,'another user cannot remove this cart');
 $wf->cart_change($user,$selected,true);wf_check(count($wf->cart($user)['items'])===2,'repeated cart addition is idempotent');
 $options=['issuer'=>'Società prova','date'=>'2026-11-01','notes'=>'Test','allocation'=>'half','format'=>'both'];
 $rows=$repo->refund_preview($selected)['items'];$expected=array_column($rows,'price_cents','id');$expected=array_map('intval',$expected);
 $input=['ids'=>$selected,'options'=>$options,'prices'=>$expected,'request_key'=>str_repeat('a',32)];
 $bad=$input;$bad['prices'][$selected[1]]=1;
 wf_reject(fn()=>$wf->issue($user,$bad),'changed prices rejected before issuance');
 wf_check(count($wf->cart($user)['items'])===2&&!(int)$repo->refund_preview($selected)['items'][0]['reimbursed'],'failed issue preserves cart and refund status');
 wf_reject(fn()=>$wf->issue($other,$input),'another user cannot issue from this cart');
 $bad=$input;$bad['options']['date']='2026-02-31';wf_reject(fn()=>$wf->issue($user,$bad),'invalid receipt date rejected');
 add_option('fr_issue_lock',time(),'',false);wf_reject(fn()=>$wf->issue($user,$input),'concurrent issuance locked');delete_option('fr_issue_lock');
 $emission=$wf->issue($user,$input);
 wf_check($emission['total_cents']===array_sum($expected)&&count($emission['items'])===2,'issue snapshots exact prices and inscriptions');
 wf_check($wf->cart($user)['items']===[],'issued rows removed from personal cart');
 wf_check((int)$repo->refund_preview($selected)['items'][0]['reimbursed']===1,'issued rows marked reimbursed');
 wf_check(str_ends_with($emission['created_at'],'Z')&&strtotime($emission['created_at'])!==false,'issuance has real UTC timestamp');
 wf_check($wf->issue($user,$input)['id']===$emission['id'],'repeated issue request returns original emission');
 wf_reject(fn()=>$wf->cart_change($other,$selected,true),'already reimbursed rows cannot enter a new cart');
 $repo->set_manual_price((int)$rows[0]['race_id'],9999,1);
 wf_check($wf->emission($emission['id'])['total_cents']===$emission['total_cents'],'issued amount immutable after price edit');
 $original=$config;$foreign=$config;$foreign['society_code']='999';update_option(FR_Settings::OPTION,$foreign);
 wf_check($wf->cart($user)['items']===[],'cart isolated by society');wf_reject(fn()=>$wf->emission($emission['id']),'emission inaccessible from another society');update_option(FR_Settings::OPTION,$original);
 $wf->cart_change($user,[$ids[2]],true);$entry_table=FR_Data_Repository::table('iscrizioni');$wpdb->update($entry_table,['active'=>0],['id'=>$ids[2]]);
 wf_check($wf->cart($user)['items'][0]['stale']===true,'stale cart entry remains removable');
 $stale=$input;$stale['ids']=[$ids[2]];$stale['request_key']=str_repeat('b',32);wf_reject(fn()=>$wf->issue($user,$stale),'inactive inscription cannot be issued');$wpdb->update($entry_table,['active'=>1],['id'=>$ids[2]]);
}finally{
 delete_option('fr_issue_lock');$wpdb->query("DELETE FROM {$prefix}carrello WHERE user_id IN (90001,90002)");$wpdb->query("DELETE FROM {$prefix}emissioni WHERE user_id IN (90001,90002)");
 foreach($ids as $id)$wpdb->update(FR_Data_Repository::table('iscrizioni'),['active'=>1],['id'=>$id]);
 foreach($prices as $price)$repo->set_manual_price((int)$price['id'],$price['manual_cents']===null?null:(int)$price['manual_cents'],1);
 $wpdb->query("DELETE FROM {$prefix}rimborsi");foreach($original_paid as $row)$wpdb->insert($prefix.'rimborsi',$row);
 update_option(FR_Settings::OPTION,$config);
}
echo "$checks workflow checks passed.\n";
