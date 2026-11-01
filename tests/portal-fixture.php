<?php
if(PHP_SAPI!=='cli')exit;
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';require __DIR__.'/.runtime/wordpress/wp-load.php';
$repo=new FR_Data_Repository();$prefix=$wpdb->prefix.'fitet_ricevute_';$backup=__DIR__.'/.runtime/portal-fixture-backup.json';
if(($argv[1]??'')==='clean'){
 $snapshot=json_decode(file_get_contents($backup),true);
 foreach(['carrello','emissioni','rimborsi'] as $name){$wpdb->query("DELETE FROM {$prefix}$name");foreach($snapshot[$name] as $row)$wpdb->insert($prefix.$name,$row);}
 $ids=$wpdb->get_col("SELECT id FROM {$prefix}gare WHERE source_tournament IN ('77770','77771')");
 foreach($ids as $id){$wpdb->delete($prefix.'iscrizioni',['race_id'=>(int)$id]);$wpdb->delete($prefix.'prezzi',['race_id'=>(int)$id]);$wpdb->delete($prefix.'gare',['id'=>(int)$id]);}
 foreach($snapshot['prices'] as $r)$wpdb->update($prefix.'prezzi',$r,['race_id'=>$r['race_id']]);
 echo "Fixture restored.\n";exit;
}
$snapshot=[];foreach(['carrello','emissioni','rimborsi'] as $name)$snapshot[$name]=$wpdb->get_results("SELECT * FROM {$prefix}$name",ARRAY_A);
$snapshot['prices']=$wpdb->get_results("SELECT * FROM {$prefix}prezzi",ARRAY_A);file_put_contents($backup,wp_json_encode($snapshot));
foreach(['carrello','emissioni','rimborsi'] as $name)$wpdb->query("DELETE FROM {$prefix}$name");
$repo->import_catalog([['key'=>hash('sha256','portal-empty-fixture'),'url'=>'https://portale.fitet.org/login/iscrizioni/iscrizioni_torneo_reg.asp?TORNEO=77771&COMITATO=4&TIPO=36','committee'=>'4','number'=>'77771','name'=>'Torneo senza iscrizioni','date'=>'2026-12-01','place'=>'Milano']],'718');
$receipt=(new FR_Receipt_Parser())->parse(__DIR__.'/.runtime/ricevuta-esempio.pdf');$receipt['entries']=array_slice($receipt['entries'],0,1);$receipt['total_cents']=1500;$receipt['metadata']['tournament_date']='2025-10-31';$receipt['metadata']['tournament_name']='Torneo stagione precedente';
$repo->import(FR_Receipt_Source::identify('https://portale.fitet.org/fpdf2/iscrizioni_gara_societa_reg.php?TORNEO=77770&COMITATO=4&ANNO=40&SOCIETA=718'),$receipt,'fixture.pdf',str_repeat('a',64));
echo "Portal fixture prepared.\n";
