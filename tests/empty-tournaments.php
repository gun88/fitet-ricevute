<?php
if(PHP_SAPI!=='cli'){exit;}
$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';
require __DIR__.'/.runtime/wordpress/wp-load.php';
function empty_check($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$repo=new FR_Data_Repository();$parser=new FR_Receipt_Parser();
$receipt=$parser->parse(__DIR__.'/.runtime/ricevuta-esempio.pdf');
$source=FR_Receipt_Source::identify('https://portale.fitet.org/fpdf2/iscrizioni_gara_societa_reg.php?TORNEO=8&ANNO=41&COMITATO=4&SOCIETA=718');
$race=$repo->listing('gare')['items'][0];$id=(int)$race['id'];$old=$race['manual_cents'];
try{
 $repo->set_manual_price($id,1234,1);
 $zero=$receipt;$zero['entries']=[];$zero['total_cents']=0;
 $repo->import($source,$zero,'fixture.pdf',str_repeat('a',64));
 $rows=$repo->listing('gare')['items'];
 empty_check(count($rows)===1&&$rows[0]['race_kind']==='empty','zero-entry tournament remains visible once');
 empty_check((int)$rows[0]['entry_count']===0&&$repo->listing('iscrizioni')['total']===0,'no invented registrations for empty tournament');
 empty_check($rows[0]['price_cents']===null&&$rows[0]['reconciliation']==='match','zero total reconciles without inventing unit price');
 $failed=false;try{$repo->set_manual_price((int)$rows[0]['id'],100,1);}catch(RuntimeException $e){$failed=true;}
 empty_check($failed,'placeholder cannot be priced as a real race');
 $repo->import($source,$zero,'fixture.pdf',str_repeat('a',64));
 empty_check($repo->listing('gare')['total']===1,'empty tournament resync is idempotent');
 $repo->import($source,$receipt,'fixture.pdf',str_repeat('a',64));
 $rows=$repo->listing('gare')['items'];$restored=array_values(array_filter($rows,fn($r)=>(int)$r['id']===$id))[0];
 empty_check(count($rows)===2&&$repo->listing('iscrizioni')['total']===6,'real races replace empty placeholder when registrations return');
 empty_check((int)$restored['manual_cents']===1234,'manual prices survive empty to populated transition');
 $national=FR_Receipt_Source::identify('https://portale.fitet.org/fpdf2/iscrizioni_gara_societa.php?TORNEO=939&ANNO=41&SOCIETA=718');
 $equivalent=FR_Receipt_Source::identify('https://portale.fitet.org/fpdf2/iscrizioni_gara_societa_reg.php?TORNEO=939&ANNO=41&SOCIETA=718&COMITATO=30');
 empty_check($national['tournament_key']===$equivalent['tournament_key'],'national receipt endpoints share identity and committee 30');
}finally{$repo->import($source,$receipt,'fixture.pdf',str_repeat('a',64));$repo->set_manual_price($id,$old===null?null:(int)$old,1);}
