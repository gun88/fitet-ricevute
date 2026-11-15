<?php
defined('ABSPATH') || exit;
final class FR_Refund_Amounts {
    public static function calculate(array $rows, array &$options): int {
        $payment=$options['payment_method']??'transfer';
        if(!in_array($payment,['transfer','cash'],true))throw new RuntimeException('Modalità di pagamento non valida.');
        $options['payment_method']=$payment;$people=[];
        $modes=$options['line_modes']??[];if(!is_array($modes)||count($modes)>1000)throw new RuntimeException('Trattamento quote non valido.');$selected=array_map('strval',array_column($rows,'id'));$options['line_modes']=array_intersect_key($modes,array_flip($selected));
        foreach($options['line_modes'] as $mode)if(!in_array($mode,['reimburse','deduct','paid'],true))throw new RuntimeException('Trattamento quote non valido.');
        foreach($rows as $row){
            $amount=(int)($row['paid_cents']??$row['price_cents']);$double=!empty($row['athlete2_name']);$allocation=$options['allocation'];
            $mode=$options['line_modes'][(string)$row['id']]??'reimburse';
            if(!in_array($mode,['reimburse','deduct','paid'],true))throw new RuntimeException('Trattamento quota non valido.');
            $shares=$double?($allocation==='first'?[$amount,0]:($allocation==='second'?[0,$amount]:[(int)ceil($amount/2),(int)floor($amount/2)])):[$amount,0];
            foreach([1,2] as $i){if(empty($row['athlete'.$i.'_name'])||($double&&(($allocation==='first'&&$i===2)||($allocation==='second'&&$i===1))))continue;
                $card=$row['athlete'.$i.'_card']??'';$name=$row['athlete'.$i.'_name'];$key=$card?'card:'.$card:'name:'.mb_strtoupper(trim($name));
                $people[$key]??=['name'=>$name,'card'=>$card?:'—','cents'=>0];$people[$key]['cents']+=$shares[$i-1]*($mode==='paid'?0:($mode==='deduct'?-1:1));
            }
        }
        $adjustments=$options['adjustments']??[];
        if(!is_array($adjustments)||count($adjustments)>500)throw new RuntimeException('Troppe rettifiche.');$clean=[];
        foreach($adjustments as $a){$key=$a['beneficiary']??'';$reason=$a['reason']??'';$cents=$a['cents']??null;
            if(!is_string($key)||!isset($people[$key])||!is_string($reason)||trim($reason)===''||mb_strlen($reason)>500||!is_int($cents)||abs($cents)>100000000||$cents===0)throw new RuntimeException('Rettifica: scegli un beneficiario, una causale e un importo positivo o negativo.');
            $clean[]=['beneficiary'=>$key,'reason'=>sanitize_textarea_field($reason),'cents'=>$cents];$people[$key]['cents']+=$cents;
        }
        foreach($people as $person)if($person['cents']<0)throw new RuntimeException('Il saldo da consegnare a un atleta non può essere negativo.');
        $methods=$options['payment_methods']??[];
        if(!is_array($methods)||count($methods)>2000)throw new RuntimeException('Modalità di pagamento per atleta non valide.');
        foreach($methods as $method)if(!in_array($method,['transfer','cash'],true))throw new RuntimeException('Scegli bonifico o contanti per ogni atleta.');
        $normalized=[];$totals=['transfer'=>0,'cash'=>0];
        foreach($people as $key=>&$person){$method=$methods[$key]??$payment;$person['payment_method']=$method;$normalized[$key]=$method;$totals[$method]+=$person['cents'];}unset($person);
        $options['payment_methods']=$normalized;$options['payment_totals']=$totals;
        $options['adjustments']=$clean;$options['settlement']=$people;
        return array_sum(array_column($people,'cents'));
    }
}
