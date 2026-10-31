<?php
// Explicit read-only public PDF check. No live account, credentials or database imports.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('zend.exception_ignore_args','1');
define('WP_ACCESSIBLE_HOSTS','portale.fitet.org');
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';
require __DIR__.'/.runtime/wordpress/wp-load.php';remove_all_filters('pre_http_request');
$links=json_decode(file_get_contents(__DIR__.'/.runtime/live-receipt-links.json'),true,512,JSON_THROW_ON_ERROR);
$directory=__DIR__.'/.runtime/live-receipts-pdf';wp_mkdir_p($directory);
$report=['receipts'=>count($links),'parsed'=>0,'entries'=>0,'single'=>0,'double'=>0,'failures'=>[]];
foreach($links as $index=>$url){
 try{
  $source=FR_Receipt_Source::identify($url,'718');
  $response=wp_safe_remote_get($source['url'],['timeout'=>20,'redirection'=>0,'cookies'=>[],'limit_response_size'=>3*1024*1024+1]);
  if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)throw new RuntimeException('Download PDF fallito.');
  $pdf=wp_remote_retrieve_body($response);
  if(!str_starts_with($pdf,'%PDF-')||strlen($pdf)>3*1024*1024)throw new RuntimeException('Risposta non PDF.');
  $path=$directory.'/'.$source['tournament_key'].'.pdf';file_put_contents($path,$pdf);
  $receipt=(new FR_Receipt_Parser())->parse($path);
  if($receipt['metadata']['society_code']!=='718')throw new RuntimeException('Società non corrispondente.');
  $report['parsed']++;$report['entries']+=count($receipt['entries']);
  foreach($receipt['entries'] as $entry){$report[$entry['race_kind']]++;}
 }catch(Throwable $error){$report['failures'][]=['receipt'=>$index+1,'message'=>$error->getMessage()];}
 echo 'Receipt '.($index+1).'/'.count($links).': '.($report['parsed']===($index+1)?'OK':'checked')."\n";
}
file_put_contents(__DIR__.'/.runtime/live-pdf-report.json',wp_json_encode($report,JSON_PRETTY_PRINT));
echo wp_json_encode($report,JSON_PRETTY_PRINT)."\n";
exit($report['failures']?1:0);
