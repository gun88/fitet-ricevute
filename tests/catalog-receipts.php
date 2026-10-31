<?php
if(PHP_SAPI!=='cli')exit;
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';
require __DIR__.'/.runtime/wordpress/wp-load.php';
function catalog_check($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$repo=new FR_Data_Repository();$sync=new FR_Sync_Service($repo);
update_option('fr_test_mock_extranet',true,false);update_option('fr_test_extranet_failure','catalog_only',false);update_option('fr_test_http_trace',[],false);
$g=FR_Data_Repository::table('gare');$p=FR_Data_Repository::table('prezzi');
try{
 $job=$sync->start(1);
 catalog_check($job['total']===1,'only actual receipt list contributes download jobs');
 $rows=$repo->listing('gare')['items'];$catalog=array_values(array_filter($rows,fn($r)=>$r['has_receipt']===false));
 catalog_check(count($catalog)===2,'regional and national catalog-only tournaments created without PDF');
 catalog_check(array_sum(array_column($catalog,'entry_count'))===0,'catalog tournaments have zero entries');
 catalog_check($catalog[0]['reconciliation']==='not_available','catalog row does not claim an original PDF total');
 $job=$sync->step($job['id'],1);$trace=get_option('fr_test_http_trace');
 catalog_check($job['done']&&$job['imported']===1&&!$job['errors'],'single listed public receipt imported');
 catalog_check(count(array_filter($trace,fn($r)=>$r[0]==='POST'))===1,'one login for entire synchronization');
 catalog_check(count(array_filter($trace,fn($r)=>str_contains($r[1],'/fpdf2/')))===1,'no download attempted for catalog-only tournaments');
 catalog_check(!str_contains(serialize($trace),'_left.asp')&&!str_contains(serialize($trace),'/iscrizioni_torneo'),'no tournament detail or frame requested');
 $job=$sync->start(1);$sync->step($job['id'],1);
 catalog_check($repo->listing('gare')['total']===count($rows),'resync creates no duplicate catalog or receipt tournaments');
 catalog_check((int)$wpdb->get_var("SELECT COUNT(*) FROM $g WHERE active=1 AND pdf_file='' AND source_year<>''")===0,'catalog never invents receipt ANNO');
 $receipt=(new FR_Receipt_Parser())->parse(__DIR__.'/.runtime/ricevuta-esempio.pdf');$receipt['metadata']['tournament_date']='2026-12-01';
 $source=FR_Receipt_Source::identify('https://portale.fitet.org/fpdf2/iscrizioni_gara_societa_reg.php?TORNEO=99999&COMITATO=4&ANNO=41&SOCIETA=718');
 $repo->import($source,$receipt,'fixture.pdf',str_repeat('a',64));
 catalog_check((int)$wpdb->get_var("SELECT COUNT(*) FROM $g WHERE source_tournament='99999' AND source_committee='4' AND active=1 AND pdf_file=''")===0,'receipt replaces matching catalog placeholder by observed ID committee and date');
 $race_id=(int)$wpdb->get_var("SELECT id FROM $g WHERE source_tournament='99999' AND source_committee='4' AND active=1 LIMIT 1");$repo->set_manual_price($race_id,1700,1);
 $job=$sync->start(1);$sync->step($job['id'],1);
 catalog_check((int)$wpdb->get_var($wpdb->prepare("SELECT manual_cents FROM $p WHERE race_id=%d",$race_id))===1700,'catalog refresh preserves authoritative receipt and manual price');
}finally{
 delete_option('fr_test_extranet_failure');
 $ids=$wpdb->get_col("SELECT id FROM $g WHERE source_tournament='99999'");
 foreach($ids as $id){$wpdb->delete(FR_Data_Repository::table('iscrizioni'),['race_id'=>(int)$id]);$wpdb->delete($p,['race_id'=>(int)$id]);$wpdb->delete($g,['id'=>(int)$id]);}
}
