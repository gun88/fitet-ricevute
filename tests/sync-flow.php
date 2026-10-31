<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['HTTP_HOST']='127.0.0.1:8095'; $_SERVER['REQUEST_URI']='/';
require __DIR__.'/.runtime/wordpress/wp-load.php';
$checks=0;
function sync_check(bool $ok,string $message):void { global $checks;if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";$checks++; }
$config=FR_Settings::all();$config['society_code']='718';update_option(FR_Settings::OPTION,$config);
FR_Extranet_Credentials::save(['username'=>'Societa.Fixture','password'=>'Fixture-FITeT-2026!']);
update_option('fr_test_mock_extranet',true,false);delete_option('fr_test_extranet_failure');
$client=new FR_Extranet_Client();$repo=new FR_Data_Repository();
$before=$repo->listing('iscrizioni');
try {
 update_option('fr_test_http_trace',[],false);
 $links=$client->discover('718');$trace=get_option('fr_test_http_trace');
 sync_check(count($links['tournaments'])===1&&count($links['receipts'])===1,'catalogs and receipt list deduplicated separately');
 sync_check(array_column($trace,0)===['GET','POST','GET','GET','GET','GET'],'one login for both catalogs and receipt list');
 sync_check(array_map(fn($r)=>wp_parse_url($r[1],PHP_URL_PATH),$trace)===['/login/login.asp','/login/autentica.asp','/login/gestione_user.asp','/login/iscrizioni/testa_iscrizioni_reg.asp','/login/iscrizioni/testa_iscrizioni.asp','/login/iscrizioni/lista_partecipazione_tornei.asp'],'only three read-only lists scanned');
 $property=new ReflectionProperty(FR_Extranet_Client::class,'cookies');$property->setAccessible(true);
 sync_check($property->getValue($client)===[],'session cookies cleared after successful discovery');
 sync_check($client->discover('718')===$links,'reused client creates a new independent ASP session');
 update_option('fr_test_extranet_failure','pagination',false);
 sync_check(count($client->discover('718')['tournaments'])===2,'same-list pagination discovers additional tournament');
 update_option('fr_test_extranet_failure','no_receipts',false);
 sync_check($client->discover('718')===['tournaments'=>[],'receipts'=>[]],'empty lists return no invented IDs');
 foreach(['bad_credentials','foreign_redirect','destructive_redirect','session_expired','oversized','many_pages'] as $mode) {
  update_option('fr_test_extranet_failure',$mode,false);update_option('fr_test_http_trace',[],false);
  $failed=false;try{$client->discover('718');}catch(RuntimeException $error){$failed=true;}
  sync_check($failed,'discovery fails explicitly: '.$mode);
  sync_check($property->getValue($client)===[],'session cleared after failure: '.$mode);
  $serialized=serialize(get_option('fr_test_http_trace'));
  sync_check(!str_contains($serialized,'example.invalid')&&!str_contains($serialized,'elimina_iscrizione'),'unapproved destinations never requested: '.$mode);
 }
 sync_check($repo->listing('iscrizioni')===$before,'discovery failures leave stored receipt data unchanged');
 delete_option('fr_test_extranet_failure');
 $service=new FR_Sync_Service($repo);$job=$service->start(1);
 $stored=get_transient('fr_sync_'.$job['id']);
 sync_check(!str_contains(serialize($stored),'Fixture-FITeT-2026!')&&!str_contains(serialize($stored),'ASPSESSION'),'sync job persists no credential or ASP session');
 delete_transient('fr_sync_'.$job['id']);
} finally { delete_option('fr_test_extranet_failure'); }
echo "$checks synchronization flow checks passed.\n";
