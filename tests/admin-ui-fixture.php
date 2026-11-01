<?php
if(PHP_SAPI!=='cli')exit;
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';require __DIR__.'/.runtime/wordpress/wp-load.php';
$file=__DIR__.'/.runtime/admin-ui-original.json.gz';$options=__DIR__.'/.runtime/admin-ui-options.json';
if(($argv[1]??'')==='clean'){
 FR_Backup::restore(FR_Backup::decode(file_get_contents($file)));
 foreach(json_decode(file_get_contents($options),true) as $key=>$value){if($value===null)delete_option($key);else update_option($key,$value,false);}echo "Admin fixture restored.\n";exit;
}
$data=FR_Backup::export();FR_Backup::validate($data);file_put_contents($file,FR_Backup::encode($data));$snapshot=[];
foreach([FR_Settings::OPTION,FR_Extranet_Credentials::OPTION,FR_Signature::OPTION,FR_Signature::STAMP_OPTION] as $key)$snapshot[$key]=get_option($key,null);file_put_contents($options,wp_json_encode($snapshot));
echo "Admin fixture saved.\n";
