<?php
if(PHP_SAPI!=='cli')exit;
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';require __DIR__.'/.runtime/wordpress/wp-load.php';
$checks=0;function sig_check($ok,$text){global $checks;if(!$ok)throw new RuntimeException($text);$checks++;echo "PASS $text\n";}
function sig_reject($f,$text){$ok=false;try{$f();}catch(RuntimeException $e){$ok=true;}sig_check($ok,$text);}
$old=FR_Signature::snapshot();$repo=new FR_Data_Repository();$wf=new FR_Refund_Workflow($repo);$prefix=$wpdb->prefix.'fitet_ricevute_';$user=90003;
$paid=$wpdb->get_results("SELECT * FROM {$prefix}rimborsi",ARRAY_A);$ids=array_slice(array_map('intval',array_column($repo->listing('iscrizioni')['items'],'id')),0,2);
try{
 FR_Signature::remove();sig_check(FR_Signature::snapshot()===null,'signature initially optional');
 $a=file_get_contents(__DIR__.'/.runtime/signature-a.png');FR_Signature::store($a);$first=FR_Signature::snapshot();
 sig_check($first['width']===480&&$first['height']===120&&$first['format']==='PNG','PNG signature dimensions detected');
 sig_check(base64_decode(explode(',',$first['data_url'],2)[1])===$a,'signature image preserved exactly');
 sig_check(!str_contains(wp_json_encode(FR_Settings::public_config()),'data:image'),'signature absent from anonymous config');
 $option=$wpdb->get_row($wpdb->prepare("SELECT autoload FROM $wpdb->options WHERE option_name=%s",FR_Signature::OPTION),ARRAY_A);
 sig_check(in_array($option['autoload'],['no','off','auto-off'],true),'signature option not autoloaded');
 FR_Signature::upload(['error'=>UPLOAD_ERR_NO_FILE]);sig_check(FR_Signature::snapshot()===$first,'empty upload preserves existing signature');
 sig_reject(fn()=>FR_Signature::store(str_repeat('x',1024*1024+1)),'oversized signature rejected');
 sig_reject(fn()=>FR_Signature::store(file_get_contents(__DIR__.'/.runtime/invalid-signature.svg')),'SVG rejected');
 sig_reject(fn()=>FR_Signature::store(str_repeat('not an image',20)),'nonimage rejected');
 sig_check(FR_Signature::snapshot()===$first,'invalid replacements preserve previous signature');
 FR_Signature::store(file_get_contents(__DIR__.'/.runtime/signature.jpg'));sig_check(FR_Signature::snapshot()['format']==='JPEG','JPEG accepted');FR_Signature::store($a);
 $repo->set_reimbursed($ids,false,1);$wf->cart_change($user,$ids,true);$rows=$repo->refund_preview($ids)['items'];$prices=array_map('intval',array_column($rows,'price_cents','id'));
 $options=['issuer'=>'Firma test','date'=>'2026-11-01','notes'=>'','allocation'=>'half','format'=>'total','signature'=>$first];
 $input=['ids'=>$ids,'options'=>$options,'prices'=>$prices,'request_key'=>str_repeat('c',32)];
 FR_Signature::store(file_get_contents(__DIR__.'/.runtime/signature-b.png'));sig_reject(fn()=>$wf->issue($user,$input),'signature changed before issue rejects outdated prepared PDF');
 sig_check(count($wf->cart($user)['items'])===2,'signature mismatch leaves cart untouched');
 FR_Signature::store($a);$emission=$wf->issue($user,$input);sig_check($emission['options']['signature']['sha256']===$first['sha256'],'issued signature saved in immutable snapshot');
 FR_Signature::remove();sig_check($wf->emission($emission['id'])['options']['signature']['data_url']===$first['data_url'],'removing configured signature preserves issued signature');
}finally{
 if($old)update_option(FR_Signature::OPTION,$old,false);else FR_Signature::remove();
 $wpdb->delete($prefix.'carrello',['user_id'=>$user]);$wpdb->delete($prefix.'emissioni',['user_id'=>$user]);$wpdb->query("DELETE FROM {$prefix}rimborsi");foreach($paid as $r)$wpdb->insert($prefix.'rimborsi',$r);
}
echo "$checks signature checks passed.\n";
