<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['HTTP_HOST']='127.0.0.1:8095'; $_SERVER['REQUEST_URI']='/';
require __DIR__.'/.runtime/wordpress/wp-load.php';
$checks=0;
function check_committee(bool $ok,string $message):void { global $checks; if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";$checks++; }
check_committee(FR_Data_Repository::tournament_scope('30')==='national','committee 30 is national');
check_committee(FR_Data_Repository::tournament_scope('030')==='national','national committee identity tolerates numeric leading zeros');
check_committee(FR_Data_Repository::tournament_scope('4')==='territorial','other known committee codes are territorial');
check_committee(FR_Data_Repository::tournament_scope('')==='unknown','missing committee is explicitly unknown');
check_committee(FR_Data_Repository::committee_label('4')==='Lombardia','committee names match public portal menu');
check_committee(FR_Data_Repository::committee_label('030')==='Nazionale','national committee label normalizes leading zeros');
check_committee(FR_Data_Repository::committee_label('7')==='Alto Adige' && FR_Data_Repository::committee_label('6')==='Trentino','province committees remain distinct');
check_committee(FR_Data_Repository::committee_label('999')==='Comitato 999' && FR_Data_Repository::committee_label('')==='Non indicato','unknown and missing labels have explicit fallbacks');
$repo=new FR_Data_Repository();$races=$repo->listing('gare')['items'];$race=$races[0];$table=FR_Data_Repository::table('gare');
try {
 $wpdb->update($table,['source_committee'=>'30'],['id'=>(int)$race['id']]);
 $race_row=array_values(array_filter($repo->listing('gare')['items'],fn($r)=>(int)$r['id']===(int)$race['id']))[0];
 check_committee($race_row['source_committee']==='30' && $race_row['tournament_scope']==='national','race API classification derives from original committee code');
 check_committee($race_row['committee_label']==='Nazionale' && $race_row['committee_name']===$race['committee_name'],'friendly name preserves original PDF committee name');
 $entry=array_values(array_filter($repo->listing('iscrizioni')['items'],fn($r)=>(int)$r['race_id']===(int)$race['id']))[0];
 check_committee($entry['tournament_scope']==='national','entry listing carries tournament scope for player history');
 check_committee($entry['committee_label']==='Nazionale','entry listing carries committee label');
 check_committee($repo->refund_preview([(int)$entry['id']])['items'][0]['tournament_scope']==='national','refund preview retains committee metadata');
 check_committee($repo->refund_preview([(int)$entry['id']])['items'][0]['committee_label']==='Nazionale','refund preview retains committee label');
} finally { $wpdb->update($table,['source_committee'=>$race['source_committee']],['id'=>(int)$race['id']]); }
echo "$checks committee checks passed.\n";
