<?php
if(PHP_SAPI!=='cli')exit;$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';require __DIR__.'/.runtime/wordpress/wp-load.php';
$checks=0;function sc($v,$label){global $checks;if(!$v)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
$backup=FR_Backup::export();$data=new FR_Data_Repository();$wf=new FR_Refund_Workflow($data);$uid=(int)(new FR_Repository())->user_by_username('fr-tester')['id'];$other=$uid+10000;$table=$wpdb->prefix.'fitet_ricevute_carrello';
try{
 $rows=array_values(array_filter($data->listing('iscrizioni')['items'],fn($r)=>empty($r['athlete2_name'])));$ids=array_map('intval',array_column(array_slice($rows,0,3),'id'));sc(count($ids)===3,'fixture available');$data->set_reimbursed($ids,false,$uid);$wpdb->query("DELETE FROM $table");
 $wf->cart_change($uid,[$ids[0]],true);sc(array_column($wf->cart($other)['items'],'id')===array_column($wf->cart($uid)['items'],'id'),'B sees A draft');
 $wf->cart_change($other,[$ids[0],$ids[1]],true);sc(count($wf->cart($uid)['items'])===2,'B additions shared without duplicates');
 $wf->update_line($other,$ids[0],['notes'=>'Nota condivisa']);sc($wf->cart($uid)['items'][0]['refund_notes']==='Nota condivisa','notes shared across operators');
 $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE entry_id=%d",$ids[0]),ARRAY_A);$r['user_id']=$other;$r['notes']='Nota legacy recente';$r['added_at']='2099-01-01 00:00:00';$wpdb->insert($table,$r);sc(count($wf->cart($uid)['items'])===2&&$wf->cart($uid)['items'][0]['refund_notes']==='Nota legacy recente','legacy personal duplicates merged deterministically');
 $wf->cart_change($other,[$ids[0]],false);sc(count($wf->cart($uid)['items'])===1&&(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE entry_id=%d",$ids[0]))===0,'remove clears all legacy copies');
 $wf->cart_change($uid,[$ids[2]],true);$preview=$data->refund_preview([$ids[1]])['items'][0];$e=$wf->issue($uid,['ids'=>[$ids[1]],'prices'=>[(string)$ids[1]=>(int)$preview['price_cents']],'request_key'=>bin2hex(random_bytes(16)),'options'=>['date'=>'2026-11-14','notes'=>'','allocation'=>'half','payment_method'=>'transfer']]);
 sc($e['user_id']===$uid,'emission attributed to issuer');sc(count($wf->cart($other)['items'])===1&&(int)$wf->cart($other)['items'][0]['id']===$ids[2],'issuance removes other operator entry and preserves remaining draft');
 $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE entry_id=%d",$ids[2]),ARRAY_A);$r['society_code']='999999';$wpdb->insert($table,$r);sc(count($wf->cart($uid)['items'])===1,'draft remains scoped to society');
 echo "$checks shared draft checks passed.\n";
}finally{FR_Backup::restore($backup);}
