<?php
if(PHP_SAPI!=='cli')exit;
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';require __DIR__.'/.runtime/wordpress/wp-load.php';
$checks=0;function ab_check($ok,$text){global $checks;if(!$ok)throw new RuntimeException($text);$checks++;echo "PASS $text\n";}
function ab_reject($fn,$text){$failed=false;try{$fn();}catch(RuntimeException $e){$failed=true;}ab_check($failed,$text);}
$options=[];foreach([FR_Settings::OPTION,FR_Extranet_Credentials::OPTION,FR_Signature::OPTION,FR_Signature::STAMP_OPTION] as $key)$options[$key]=get_option($key,null);
$prefix=$wpdb->prefix.'fitet_ricevute_';$tables=[];foreach(FR_Backup::TABLES as $name)$tables[$name]=$wpdb->get_results('SELECT * FROM '.FR_Backup::table($name),ARRAY_A);
$repo=new FR_Data_Repository();$wf=new FR_Refund_Workflow($repo);$user=(new FR_Repository())->user_by_username('fr-tester');$uid=(int)$user['id'];$original_hash=$user['password_hash'];$backup=null;
try{
 $settings=FR_Settings::all();$settings['society_name']='Società da configurazione';FR_Extranet_Credentials::save(['username'=>'Societa.Fixture','password'=>'Fixture-FITeT-2026!']);
 $input=['settings'=>$settings,'extranet'=>['username'=>'Societa.Fixture','password'=>''],'clear_signature'=>false,'clear_stamp'=>false];
 FR_Signature::store(file_get_contents(__DIR__.'/.runtime/signature-a.png'));FR_Signature::store(file_get_contents(__DIR__.'/.runtime/signature-b.png'),'stamp');
 FR_Settings::save_configuration($input,[]);ab_check(FR_Settings::all()['society_name']===$settings['society_name'],'single save persists general configuration');
 ab_check(FR_Signature::snapshot()!==null&&FR_Signature::snapshot('stamp')!==null,'single save with empty file fields preserves signature and stamp');
 ab_check(FR_Extranet_Credentials::for_sync()['password']==='Fixture-FITeT-2026!','single save preserves blank external password');
 $bad=$input;$bad['settings']['society_name']='Must not be saved';$bad['extranet']['password']='replacement-password';
 ab_reject(fn()=>FR_Settings::save_configuration($bad,['stamp_file'=>['error'=>UPLOAD_ERR_OK,'tmp_name'=>'not-uploaded','size'=>100]]),'invalid stamp rejects entire configuration');
 ab_check(FR_Settings::all()['society_name']===$settings['society_name']&&FR_Extranet_Credentials::for_sync()['password']==='Fixture-FITeT-2026!','invalid image leaves settings and credentials unchanged');
 $ids=array_slice(array_map('intval',array_column($repo->listing('iscrizioni')['items'],'id')),0,3);$repo->set_reimbursed($ids,false,$uid);$wf->cart_change($uid,$ids,true);
 $wf->update_line($uid,$ids[0],['notes'=>"Nota riga\nseconda riga"]);ab_check($wf->cart($uid)['items'][0]['refund_notes']==="Nota riga\nseconda riga",'line notes persist in database');
 ab_reject(fn()=>$wf->update_line(99999,$ids[0],['notes'=>'no']),'another user cannot edit draft notes');
 ab_reject(fn()=>$wf->update_line($uid,$ids[0],['notes'=>str_repeat('x',1001)]),'overlong row notes rejected');
 $selected=array_slice($ids,0,2);$rows=$repo->refund_preview($selected)['items'];$prices=array_map('intval',array_column($rows,'price_cents','id'));
 $emission=$wf->issue($uid,['ids'=>$selected,'prices'=>$prices,'request_key'=>str_repeat('d',32),'options'=>['issuer'=>'Client override','date'=>'2026-11-01','notes'=>"Note generali\nseconda riga",'allocation'=>'half','format'=>'both']]);
 ab_check($emission['options']['issuer']===$settings['society_name'],'emitter always comes from server configuration');
 ab_check($emission['options']['allocation']==='half','double allocation unchanged');
 ab_check($emission['options']['format']==='total','issue does not fix client print format');
 ab_check($emission['items'][0]['refund_notes']==="Nota riga\nseconda riga",'line notes included in immutable emission snapshot');
 ab_check($emission['options']['stamp']['sha256']===FR_Signature::snapshot('stamp')['sha256'],'stamp included in immutable emission snapshot');
 $backup=FR_Backup::export();FR_Backup::validate($backup);$gzip=FR_Backup::encode($backup);$decoded=FR_Backup::decode($gzip);
 ab_check(count($decoded['tables']['emissioni'])===count($backup['tables']['emissioni']),'complete backup roundtrip validated');
 $json=gzdecode($gzip);ab_check(!str_contains($json,'"password_hash"')&&!str_contains($json,'Fixture-FITeT-2026!')&&!str_contains($json,'"password_encrypted"'),'backup excludes password hashes and external credentials');
 ab_check(count($backup['pdfs'])>0,'backup includes original PDFs');
 file_put_contents(__DIR__.'/.runtime/test-complete-backup.json.gz',$gzip);
 foreach(['column','relation','path','pdf_hash','image','total'] as $case){
  $bad=$backup;
  if($case==='column')$bad['tables']['gare'][0]['sql_injection']='x';
  if($case==='relation')$bad['tables']['iscrizioni'][0]['race_id']=99999999;
  if($case==='path')$bad['pdfs']['../bad.php']=['sha256'=>'x','base64'=>''];
  if($case==='pdf_hash'){$name=array_key_first($bad['pdfs']);$bad['pdfs'][$name]['sha256']=str_repeat('0',64);}
  if($case==='image')$bad['images']['stamp']['sha256']=str_repeat('0',64);
  if($case==='total')$bad['tables']['emissioni'][0]['total_cents']++;
  ab_reject(fn()=>FR_Backup::validate($bad),'backup rejects invalid '.$case);
 }
 $repo->set_manual_price((int)$repo->listing('gare')['items'][0]['id'],9999,$uid);$wf->cart_change($uid,[$ids[2]],false);FR_Signature::replace(null,'stamp');
 $before_failure=wp_json_encode($wpdb->get_results('SELECT * FROM '.FR_Backup::table('prezzi'),ARRAY_A));
 $broken=$backup;$pdf=file_get_contents(__DIR__.'/.runtime/ricevuta-esempio.pdf');$broken['pdfs']['rollback-test.pdf']=['base64'=>base64_encode($pdf),'sha256'=>hash('sha256',$pdf)];
 $filter=function($sql)use($prefix){return stripos($sql,'INSERT INTO')!==false&&str_contains($sql,$prefix.'prezzi')?'INSERT INTO fr_forced_missing_table (id) VALUES (1)':$sql;};
 add_filter('query',$filter,999);$wpdb->suppress_errors(true);
 try{ab_reject(fn()=>FR_Backup::restore($broken),'database failure aborts restore');}finally{remove_filter('query',$filter,999);$wpdb->suppress_errors(false);}
 ab_check(wp_json_encode($wpdb->get_results('SELECT * FROM '.FR_Backup::table('prezzi'),ARRAY_A))===$before_failure,'failed restore rolls database deletions and inserts back');
 $uploads=wp_upload_dir();ab_check(!is_file($uploads['basedir'].'/fitet-ricevute/ricevute-pdf/rollback-test.pdf'),'failed restore removes newly written PDFs');
 FR_Backup::restore($backup);
 ab_check(count($wf->cart($uid)['items'])===count($backup['tables']['carrello']),'restore recovers persistent drafts');
 ab_check(FR_Signature::snapshot('stamp')['sha256']===$backup['images']['stamp']['sha256'],'restore recovers stamp configuration');
 ab_check($wf->emission($emission['id'])['total_cents']===$emission['total_cents'],'restore recovers issued refund amount');
 ab_check((new FR_Repository())->user($uid)['password_hash']===$original_hash,'restore preserves destination account password');
 ab_check(FR_Extranet_Credentials::for_sync()['password']==='Fixture-FITeT-2026!','restore preserves destination external credentials');
 $extra=$backup;$extra['users'][]=['id'=>777777,'username'=>'backup-new-disabled','display_name'=>'Nuovo da backup','enabled'=>1,'created_at'=>'2026-11-01 00:00:00'];FR_Backup::restore($extra);
 $created=(new FR_Repository())->user_by_username('backup-new-disabled');ab_check($created&&!(int)$created['enabled'],'unmapped imported account created disabled');
 if($created)$wpdb->delete(FR_Repository::users_table(),['id'=>$created['id']]);
}finally{
 foreach(array_reverse(FR_Backup::TABLES) as $name)$wpdb->query('DELETE FROM '.FR_Backup::table($name));
 foreach($tables as $name=>$records)foreach($records as $record)$wpdb->insert(FR_Backup::table($name),$record);
 foreach($options as $key=>$value){if($value===null)delete_option($key);else update_option($key,$value,false);}
}
echo "$checks admin/draft/backup checks passed.\n";
