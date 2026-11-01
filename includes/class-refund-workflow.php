<?php
defined('ABSPATH') || exit;
final class FR_Refund_Workflow {
    public function __construct(private FR_Data_Repository $data) {}
    private function table(string $name): string { global $wpdb; return $wpdb->prefix.'fitet_ricevute_'.$name; }
    private function society(): string {
        $code=(string)FR_Settings::all()['society_code'];
        if($code==='')throw new RuntimeException('Configura il codice società.');
        return ltrim($code,'0')?:'0';
    }
    private function checked($result): void { if($result===false)throw new RuntimeException('Salvataggio non riuscito: nessuna emissione parziale.'); }
    public function cart(int $user): array {
        global $wpdb;$table=$this->table('carrello');$society=$this->society();
        $stored=$wpdb->get_results($wpdb->prepare("SELECT entry_id,snapshot_json FROM $table WHERE user_id=%d AND society_code=%s ORDER BY added_at,entry_id",$user,$society),ARRAY_A);
        $items=[];$current=[];
        if($stored){
            try{foreach($this->data->refund_preview(array_map('intval',array_column($stored,'entry_id')))['items'] as $row)$current[(int)$row['id']]=$row;}
            catch(RuntimeException $error){ /* Resolve individually only when the cart contains stale source rows. */ }
        }
        foreach($stored as $item){
            try{$row=$current[(int)$item['entry_id']]??$this->data->refund_preview([(int)$item['entry_id']])['items'][0];$row['stale']=false;}
            catch(RuntimeException $error){$row=json_decode($item['snapshot_json'],true);$row['stale']=true;}
            $items[]=$row;
        }
        return ['items'=>$items];
    }
    public function cart_change(int $user,array $ids,bool $add): array {
        global $wpdb;$table=$this->table('carrello');$society=$this->society();
        if(!$add){foreach($ids as $id)$this->checked($wpdb->delete($table,['user_id'=>$user,'society_code'=>$society,'entry_id'=>(int)$id]));return $this->cart($user);}
        $cart_lock='fr_cart_lock_'.hash('sha256',$user.':'.$society);$old=get_option($cart_lock);
        if($old&&time()-(int)$old>120)delete_option($cart_lock);
        if(!add_option($cart_lock,time(),'',false))throw new RuntimeException('Il carrello è già in aggiornamento. Riprova tra poco.');
        try {
        $rows=$this->data->refund_preview($ids)['items'];
        foreach($rows as $row)if((int)$row['reimbursed'])throw new RuntimeException('Una quota è già rimborsata. Aggiorna la selezione.');
        $existing=$wpdb->get_col($wpdb->prepare("SELECT entry_id FROM $table WHERE user_id=%d AND society_code=%s",$user,$society));
        if(count(array_unique(array_merge(array_map('intval',$existing),$ids)))>1000)throw new RuntimeException('Il carrello può contenere al massimo 1000 quote.');
        $this->checked($wpdb->query('START TRANSACTION'));
        try{
            foreach($rows as $row){
                if(in_array((int)$row['id'],array_map('intval',$existing),true))continue;
                $this->checked($wpdb->insert($table,['user_id'=>$user,'society_code'=>$society,'entry_id'=>(int)$row['id'],'snapshot_json'=>wp_json_encode($row),'added_at'=>current_time('mysql',true)]));
            }
            $this->checked($wpdb->query('COMMIT'));
        }catch(Throwable $error){$wpdb->query('ROLLBACK');throw $error;}
        return $this->cart($user);
        } finally { delete_option($cart_lock); }
    }
    private function decode(array $row): array {
        $row['id']=(int)$row['id'];$row['user_id']=(int)$row['user_id'];$row['total_cents']=(int)$row['total_cents'];
        $row['created_at']=str_replace(' ','T',$row['created_at']).'Z';
        $row['items']=json_decode($row['items_json'],true);$row['options']=json_decode($row['options_json'],true);
        unset($row['items_json'],$row['options_json'],$row['request_key']);return $row;
    }
    public function emissions(int $page): array {
        global $wpdb;$table=$this->table('emissioni');$society=$this->society();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE society_code=%s ORDER BY id DESC LIMIT 50 OFFSET %d",$society,(max(1,$page)-1)*50),ARRAY_A);
        return ['items'=>array_map(function($row){$value=$this->decode($row);if(isset($value['options']['signature']['data_url']))unset($value['options']['signature']['data_url']);return $value;},$rows),'total'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE society_code=%s",$society)),'page'=>$page,'per_page'=>50];
    }
    public function emission(int $id): array {
        global $wpdb;$table=$this->table('emissioni');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND society_code=%s",$id,$this->society()),ARRAY_A);
        if(!$row)throw new RuntimeException('Rimborso non disponibile.');return $this->decode($row);
    }
    public function issue(int $user,array $input): array {
        global $wpdb;$society=$this->society();$emissions=$this->table('emissioni');$cart=$this->table('carrello');$paid=FR_Data_Repository::table('rimborsi');
        $key=$input['request_key']??'';$options=$input['options']??[];$ids=$input['ids'];
        if(!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Identificativo emissione non valido.');
        $date=$options['date']??'';$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!$parsed||$parsed->format('Y-m-d')!==$date||!is_string($options['issuer']??null)||trim($options['issuer'])===''||mb_strlen($options['issuer'])>180
            ||!is_string($options['notes']??null)||mb_strlen($options['notes'])>500
            ||!in_array($options['allocation']??'',['half','first','second'],true)||!in_array($options['format']??'',['total','athletes','both'],true))throw new RuntimeException('Impostazioni ricevuta non valide.');
        // Serialize issuance across users; expired locks can be recovered after interrupted requests.
        $lock='fr_issue_lock';$old=get_option($lock);
        if($old&&time()-(int)$old>120)delete_option($lock);
        if(!add_option($lock,time(),'',false))throw new RuntimeException('Un’emissione è già in corso. Riprova tra poco.');
        try{
            $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM $emissions WHERE user_id=%d AND society_code=%s AND request_key=%s",$user,$society,$key));
            if($existing)return $this->emission((int)$existing);
            $signature=FR_Signature::snapshot();
            if(($options['signature']['sha256']??null)!==($signature['sha256']??null))throw new RuntimeException('La firma è cambiata: ripeti l’emissione per aggiornare le ricevute.');
            $options['signature']=$signature;
            $this->checked($wpdb->query('START TRANSACTION'));
            try{
                $cart_ids=array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT entry_id FROM $cart WHERE user_id=%d AND society_code=%s",$user,$society)));
                if(array_diff($ids,$cart_ids))throw new RuntimeException('La selezione non è più nel tuo carrello. Aggiorna la pagina.');
                $rows=$this->data->refund_preview($ids)['items'];$total=0;$now=current_time('mysql',true);
                foreach($rows as &$row){
                    $id=(int)$row['id'];$amount=$row['price_cents'];
                    if((int)$row['reimbursed']||$amount===null)throw new RuntimeException('Quote già rimborsate o prive di prezzo: verifica il carrello.');
                    if(!isset($input['prices'][(string)$id])||!is_int($input['prices'][(string)$id])||$input['prices'][(string)$id] !== (int)$amount)throw new RuntimeException('I prezzi sono cambiati: controlla il nuovo recap.');
                    $record=['reimbursed'=>1,'paid_cents'=>(int)$amount,'updated_at'=>$now,'updated_by'=>$user];
                    $exists=$wpdb->get_var($wpdb->prepare("SELECT entry_id FROM $paid WHERE entry_id=%d",$id));
                    if($exists){$changed=$wpdb->update($paid,$record,['entry_id'=>$id,'reimbursed'=>0]);if($changed!==1)throw new RuntimeException('Quota modificata da un’altra operazione.');}
                    else{$this->checked($wpdb->insert($paid,array_merge(['entry_id'=>$id],$record)));}
                    $total+=(int)$amount;$row['price_cents']=(int)$amount;$row['paid_cents']=(int)$amount;$row['reimbursed']=1;
                }unset($row);
                $this->checked($wpdb->insert($emissions,['user_id'=>$user,'society_code'=>$society,'request_key'=>$key,'created_at'=>$now,'total_cents'=>$total,'items_json'=>wp_json_encode($rows),'options_json'=>wp_json_encode($options)]));
                $id=(int)$wpdb->insert_id;
                foreach($ids as $entry_id)$this->checked($wpdb->delete($cart,['user_id'=>$user,'society_code'=>$society,'entry_id'=>$entry_id]));
                $this->checked($wpdb->query('COMMIT'));return $this->emission($id);
            }catch(Throwable $error){$wpdb->query('ROLLBACK');throw $error;}
        }finally{delete_option($lock);}
    }
}
