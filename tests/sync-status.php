<?php
if(PHP_SAPI!=='cli'){exit;}
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';
require __DIR__.'/.runtime/wordpress/wp-load.php';
$config=FR_Settings::all();$config['society_code']='718';update_option(FR_Settings::OPTION,$config);
$key='fr_last_sync_'.hash('sha256','718');$previous=get_option($key,null);
function status_check($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
try{
 delete_option($key);delete_option('fr_test_bad_pdf');delete_option('fr_test_extranet_failure');
 update_option('fr_test_mock_extranet',true,false);
 FR_Extranet_Credentials::save(['username'=>'Societa.Fixture','password'=>'Fixture-FITeT-2026!']);
 $sync=new FR_Sync_Service(new FR_Data_Repository());
 status_check(FR_Sync_Service::status()['last_sync_at']===null,'no invented date before first sync');
 $job=$sync->start(1);
 status_check(FR_Sync_Service::status()['last_sync_at']===null,'starting sync does not update completion date');
 $job=$sync->step($job['id'],1);$date=FR_Sync_Service::status()['last_sync_at'];
 status_check($job['done']&&!$job['errors']&&is_string($date)&&strtotime($date)!==false,'successful completion stores UTC timestamp');
 $sync->step($job['id'],1);
 status_check(FR_Sync_Service::status()['last_sync_at']===$date,'repeated completed step preserves date');
 update_option($key,'2026-01-01T12:00:00Z',false);
 update_option('fr_test_bad_pdf',true,false);$job=$sync->start(1);$sync->step($job['id'],1);
 status_check(FR_Sync_Service::status()['last_sync_at']==='2026-01-01T12:00:00Z','failed receipt preserves last successful date');
 $other=$config;$other['society_code']='999';update_option(FR_Settings::OPTION,$other);
 status_check(FR_Sync_Service::status()['last_sync_at']===null,'timestamp isolated by society');
}finally{update_option(FR_Settings::OPTION,$config);delete_option('fr_test_bad_pdf');if($previous===null)delete_option($key);else update_option($key,$previous,false);}
