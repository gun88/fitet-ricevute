<?php
if(PHP_SAPI!=='cli')exit;$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';require __DIR__.'/.runtime/wordpress/wp-load.php';
$rows=[];foreach([['A','1'],['B','2'],['C','3'],['A','1']] as $i=>$p)$rows[]=['id'=>$i+1,'athlete1_name'=>$p[0],'athlete1_card'=>$p[1],'athlete2_name'=>null,'price_cents'=>1500];
$checks=0;function ap($v,$s){global $checks;if(!$v)throw new RuntimeException($s);$checks++;echo "PASS $s\n";}
$options=['allocation'=>'half','payment_method'=>'transfer','payment_methods'=>['card:1'=>'cash','card:2'=>'cash','card:3'=>'transfer']];$total=FR_Refund_Amounts::calculate($rows,$options);
ap($total===6000,'payment choice leaves grand total unchanged');ap(count($options['settlement'])===3,'one payment per beneficiary across multiple registrations');ap($options['payment_totals']===['transfer'=>1500,'cash'=>4500],'two athletes in cash, one via transfer');ap($options['settlement']['card:1']['cents']===3000&&$options['settlement']['card:1']['payment_method']==='cash','all athlete registrations share one method');
$legacy=['allocation'=>'half','payment_method'=>'cash'];FR_Refund_Amounts::calculate($rows,$legacy);ap($legacy['payment_totals']['cash']===6000&&$legacy['payment_totals']['transfer']===0,'legacy global method preserved');
$default=['allocation'=>'half'];FR_Refund_Amounts::calculate($rows,$default);ap($default['payment_totals']['transfer']===6000,'missing legacy method defaults to transfer');
$odd=[['id'=>1,'athlete1_name'=>'A','athlete1_card'=>'1','athlete2_name'=>'B','athlete2_card'=>'2','price_cents'=>1001]];$split=['allocation'=>'half','payment_methods'=>['card:1'=>'cash','card:2'=>'transfer']];FR_Refund_Amounts::calculate($odd,$split);ap($split['payment_totals']===['transfer'=>500,'cash'=>501],'double partners may use different methods without losing odd cent');
$bad=['allocation'=>'half','payment_methods'=>['card:1'=>'invalid']];$failed=false;try{FR_Refund_Amounts::calculate($rows,$bad);}catch(RuntimeException $e){$failed=true;}ap($failed,'invalid per-athlete method rejected');
$zero=['allocation'=>'half','payment_methods'=>['card:1'=>'cash'],'adjustments'=>[['beneficiary'=>'card:1','reason'=>'Trattenuta','cents'=>-3000]]];FR_Refund_Amounts::calculate($rows,$zero);ap($zero['payment_totals']['cash']===0,'zero cash balance remains zero');
echo "$checks athlete payment checks passed.\n";
