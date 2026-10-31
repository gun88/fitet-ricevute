<?php
// Explicit read-only live check; supplied credentials remain only in this CLI process.
if(PHP_SAPI!=='cli'||empty($argv[1]))exit(2);
ini_set('zend.exception_ignore_args','1');define('WP_ACCESSIBLE_HOSTS','portale.fitet.org');
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';
require __DIR__.'/.runtime/wordpress/wp-load.php';remove_all_filters('pre_http_request');
$request=file_get_contents($argv[1]);preg_match("/--data-raw '([^']+)'/s",$request,$match);parse_str($match[1],$payload);
$credentials=['username'=>$payload['login'],'password'=>$payload['password']];unset($request,$match,$payload);
$trace=[];add_action('http_api_debug',function($response,$context,$class,$args,$url)use(&$trace){$trace[]=[$args['method'],$url];},10,5);
$scan=(new FR_Extranet_Client())->discover('718',$credentials);unset($credentials);
echo wp_json_encode(['catalog_tournaments'=>count($scan['tournaments']),'regional'=>count(array_filter($scan['tournaments'],fn($t)=>$t['committee']!=='30')),'listed_receipts'=>count($scan['receipts']),'login_posts'=>count(array_filter($trace,fn($r)=>$r[0]==='POST')),'http_requests'=>count($trace),'detail_requests'=>count(array_filter($trace,fn($r)=>str_contains($r[1],'/iscrizioni_torneo')))])."\n";

