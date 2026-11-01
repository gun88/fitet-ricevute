<?php
if (PHP_SAPI !== 'cli') exit;
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';
require __DIR__.'/.runtime/wordpress/wp-load.php';
$checks=0;
function rc($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";$checks++;}
function denied($fn,$label){$failed=false;try{$fn();}catch(RuntimeException $e){$failed=true;}rc($failed,$label);}
$backup=FR_Backup::export();$admin=get_user_by('login','wp-test-admin');wp_set_current_user($admin->ID);
$settings=FR_Settings::all();$users=(new FR_Repository())->users();
$preserved=[];foreach([FR_Signature::OPTION,FR_Signature::STAMP_OPTION,'fitet_ricevute_extranet_credentials','fr_grid_preferences_1'] as $key)$preserved[$key]=get_option($key);
$directory=wp_upload_dir()['basedir'].'/fitet-ricevute/ricevute-pdf';wp_mkdir_p($directory);$sentinel=$directory.'/reset-test.txt';file_put_contents($sentinel,'keep');
try{
 rc(FR_Development_Reset::available(),'localhost available');
 $_SERVER['HTTP_HOST']='example.org';rc(!FR_Development_Reset::available(),'external host blocked');denied(fn()=>FR_Development_Reset::run(),'external invocation refused');
 $_SERVER['HTTP_HOST']='127.0.0.1:8095';
 $production=fn()=>'https://example.org';add_filter('home_url',$production);rc(!FR_Development_Reset::available(),'production site blocked even with local request host');remove_filter('home_url',$production);
 wp_set_current_user(0);denied(fn()=>FR_Development_Reset::run(),'nonadministrator refused');wp_set_current_user($admin->ID);
 add_option('fr_backup_restore_lock',time(),'',false);denied(fn()=>FR_Development_Reset::run(),'concurrent restore blocked');delete_option('fr_backup_restore_lock');
 add_option('fr_sync_lock_'.str_repeat('a',32),time(),'',false);denied(fn()=>FR_Development_Reset::run(),'active synchronization blocked');delete_option('fr_sync_lock_'.str_repeat('a',32));
 set_transient('fr_sync_'.str_repeat('a',32),['test'=>true],600);update_option('fr_last_sync_'.str_repeat('a',64),'test');$generation=get_option('fitet_ricevute_data_generation');
 rc(FR_Development_Reset::run()===0,'reset succeeds');
 foreach(FR_Backup::TABLES as $name)rc((int)$wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix.'fitet_ricevute_'.$name)===0,'empty table '.$name);
 rc(FR_Settings::all()===$settings,'settings unchanged');rc((new FR_Repository())->users()===$users,'accounts unchanged');
 foreach($preserved as $key=>$value)rc(get_option($key)===$value,'preserved '.$key);
 rc(!get_transient('fr_sync_'.str_repeat('a',32))&&!get_option('fr_last_sync_'.str_repeat('a',64)),'sync state cleared');
 rc(get_option('fitet_ricevute_data_generation')!==$generation,'old jobs invalidated');
 rc(count(glob($directory.'/*.pdf'))===0,'archived PDFs removed');rc(is_file($sentinel),'unrelated files preserved');
 rc(!get_option('fr_backup_restore_lock'),'lock released');
 echo "$checks reset checks passed.\n";
}finally{delete_option('fr_sync_lock_'.str_repeat('a',32));delete_option('fr_last_sync_'.str_repeat('a',64));delete_transient('fr_sync_'.str_repeat('a',32));FR_Backup::restore($backup);if(is_file($sentinel))unlink($sentinel);}
