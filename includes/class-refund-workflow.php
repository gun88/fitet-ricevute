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
    // Legacy personal drafts form a shared union. Keep one row per entry, with the
    // most recently added snapshot/notes winning when an old entry is duplicated.
    private function cart_records(): array {
        global $wpdb;$table=$this->table('carrello');$society=$this->society();$unique=[];
        foreach($wpdb->get_results($wpdb->prepare("SELECT entry_id,snapshot_json,notes FROM $table WHERE society_code=%s ORDER BY added_at,user_id,entry_id",$society),ARRAY_A) as $row)$unique[(int)$row['entry_id']]=$row;
        return array_values($unique);
    }
    public function cart(int $user): array {
        global $wpdb;$table=$this->table('carrello');$society=$this->society();
        $stored=$this->cart_records();
        $items=[];$current=[];
        if($stored){
            try{foreach($this->data->refund_preview(array_map('intval',array_column($stored,'entry_id')))['items'] as $row)$current[(int)$row['id']]=$row;}
            catch(RuntimeException $error){ /* Resolve individually only when the cart contains stale source rows. */ }
        }
        foreach($stored as $item){
            try{$row=$current[(int)$item['entry_id']]??$this->data->refund_preview([(int)$item['entry_id']])['items'][0];$row['stale']=false;}
            catch(RuntimeException $error){$row=json_decode($item['snapshot_json'],true);$row['stale']=true;}
            $row['refund_notes']=$item['notes']??'';
            $items[]=$row;
        }
        return ['items'=>$items];
    }
    public function cart_change(int $user,array $ids,bool $add): array {
        global $wpdb;$table=$this->table('carrello');$society=$this->society();
        if(!$add){foreach($ids as $id)$this->checked($wpdb->delete($table,['society_code'=>$society,'entry_id'=>(int)$id]));return $this->cart($user);}
        $cart_lock='fr_cart_lock_'.hash('sha256',$society);$old=get_option($cart_lock);
        if($old&&time()-(int)$old>120)delete_option($cart_lock);
        if(!add_option($cart_lock,time(),'',false))throw new RuntimeException('La bozza rimborso è già in aggiornamento. Riprova tra poco.');
        try {
        $rows=$this->data->refund_preview($ids)['items'];
        foreach($rows as $row)if((int)$row['reimbursed'])throw new RuntimeException('Una quota è già rimborsata. Aggiorna la selezione.');
        $existing=$wpdb->get_col($wpdb->prepare("SELECT entry_id FROM $table WHERE society_code=%s",$society));
        if(count(array_unique(array_merge(array_map('intval',$existing),$ids)))>1000)throw new RuntimeException('La bozza rimborso può contenere al massimo 1000 quote.');
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
    public function update_line(int $user,int $id,array $input): array {
        global $wpdb;$table=$this->table('carrello');$society=$this->society();
        if(array_keys($input)!==['notes']||!is_string($input['notes'])||mb_strlen($input['notes'])>1000)throw new RuntimeException('Note riga: massimo 1000 caratteri.');
        $row=$wpdb->get_var($wpdb->prepare("SELECT entry_id FROM $table WHERE society_code=%s AND entry_id=%d",$society,$id));
        if(!$row)throw new RuntimeException('Riga della bozza non disponibile.');
        $this->checked($wpdb->update($table,['notes'=>sanitize_textarea_field($input['notes'])],['society_code'=>$society,'entry_id'=>$id]));return $this->cart($user);
    }
    private function draft_key(): string {return 'fr_draft_options_'.hash('sha256',$this->society());}
    public function draft_options(): array {return get_option($this->draft_key(),['revision'=>0,'options'=>null]);}
    public function save_draft_options(array $input): array {
        $key=$this->draft_key();$lock=$key.'_lock';
        $old=get_option($lock);if($old&&time()-(int)$old>120)delete_option($lock);
        if(!add_option($lock,time(),'',false))throw new RuntimeException('Bozza in aggiornamento. Riprova.');
        try{
            $stored=$this->draft_options();if(($input['revision']??null)!==$stored['revision'])throw new RuntimeException('La bozza è stata salvata da un altro operatore. Ricarica prima di modificarla.');
            $options=$input['options']??null;
            if(!is_array($options)||!in_array($options['allocation']??null,['half','first','second'],true)||!is_string($options['notes']??null)||mb_strlen($options['notes'])>2000)throw new RuntimeException('Impostazioni bozza non valide.');
            $date=$options['date']??'';$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new RuntimeException('Data ricevuta non valida.');
            $options=array_intersect_key($options,array_flip(['date','notes','allocation','payment_method','payment_methods','line_modes','adjustments']));
            FR_Refund_Amounts::calculate($this->cart(0)['items'],$options);unset($options['settlement'],$options['payment_totals']);
            $value=['revision'=>$stored['revision']+1,'options'=>$options];update_option($key,$value,false);if(get_option($key)!==$value)throw new RuntimeException('Salvataggio bozza non riuscito.');return $value;
        }finally{delete_option($lock);}
    }
    private array $operatorNames=[];
    private function operator_name(int $id):string {if(!isset($this->operatorNames[$id])){$user=(new FR_Repository())->user($id);$this->operatorNames[$id]=$user?($user['display_name']?:$user['username']):'Operatore non disponibile';}return $this->operatorNames[$id];}
    private function decode(array $row): array {
        $row['id']=(int)$row['id'];$row['user_id']=(int)$row['user_id'];$row['total_cents']=(int)$row['total_cents'];
        $row['created_at']=str_replace(' ','T',$row['created_at']).'Z';
        $row['items']=json_decode($row['items_json'],true);$row['options']=json_decode($row['options_json'],true);
        $sanitize=function(array &$options)use(&$sanitize){if(isset($options['original_receipts']))foreach($options['original_receipts'] as &$doc)unset($doc['file']);unset($doc);if(isset($options['history']))foreach($options['history'] as &$version)if(isset($version['options']))$sanitize($version['options']);};$sanitize($row['options']);
        $row['user_name']=$this->operator_name($row['user_id']);if(isset($row['options']['updated_by']))$row['options']['updated_by_name']??=$this->operator_name((int)$row['options']['updated_by']);if(isset($row['options']['history']))foreach($row['options']['history'] as &$version)$version['replaced_by_name']??=$this->operator_name((int)($version['replaced_by']??0));unset($version);
        unset($row['items_json'],$row['options_json'],$row['request_key']);return $row;
    }
    public function emissions(int $page): array {
        global $wpdb;$table=$this->table('emissioni');$society=$this->society();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE society_code=%s ORDER BY id DESC LIMIT 50 OFFSET %d",$society,(max(1,$page)-1)*50),ARRAY_A);
        return ['items'=>array_map(function($row){$value=$this->decode($row);if(isset($value['options']['history']))$value['options']['history']=array_map(fn($h)=>array_intersect_key($h,array_flip(['revision','total_cents','replaced_at','replaced_by','replaced_by_name'])),$value['options']['history']);foreach(['signature','stamp'] as $image)if(isset($value['options'][$image]['data_url']))unset($value['options'][$image]['data_url']);return $value;},$rows),'total'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE society_code=%s",$society)),'page'=>$page,'per_page'=>50];
    }
    public function emission(int $id): array {
        global $wpdb;$table=$this->table('emissioni');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND society_code=%s",$id,$this->society()),ARRAY_A);
        if(!$row)throw new RuntimeException('Rimborso non disponibile.');return $this->decode($row);
    }
    private function document_bundle(int $id): array {
        global $wpdb;$table=$this->table('emissioni');$row=$wpdb->get_row($wpdb->prepare("SELECT items_json,options_json FROM $table WHERE id=%d AND society_code=%s",$id,$this->society()),ARRAY_A);
        if(!$row)throw new RuntimeException('Rimborso non disponibile.');return FR_Refund_Documents::bundle(json_decode($row['items_json'],true),json_decode($row['options_json'],true),$this->society());
    }
    public function documents(int $id): array {return FR_Refund_Documents::manifest($this->document_bundle($id));}
    public function original_pdf(int $id,string $receipt): string {
        foreach($this->document_bundle($id)['documents'] as $doc)if(hash_equals($doc['id'],$receipt))return FR_Refund_Documents::read($doc);
        throw new RuntimeException('Ricevuta non inclusa in questo rimborso.');
    }
    public function delete_emission(int $user,int $id,int $revision): array {
        global $wpdb;$table=$this->table('emissioni');$paid=$this->table('rimborsi');$society=$this->society();
        $old=get_option('fr_issue_lock');if($old&&time()-(int)$old>120)delete_option('fr_issue_lock');
        if(!add_option('fr_issue_lock',time(),'',false))throw new RuntimeException('Un rimborso è già in aggiornamento. Riprova.');
        try{
            $this->checked($wpdb->query('START TRANSACTION'));
            try{
                $raw=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND society_code=%s FOR UPDATE",$id,$society),ARRAY_A);
                if(!$raw)throw new RuntimeException('Rimborso non disponibile.');
                $options=json_decode($raw['options_json'],true);
                if((int)($options['revision']??0)!==$revision)throw new RuntimeException('Il rimborso è stato modificato. Ricarica la pagina prima di eliminarlo.');
                $retained=[];
                foreach($wpdb->get_col($wpdb->prepare("SELECT items_json FROM $table WHERE society_code=%s AND id<>%d",$society,$id)) as $json)foreach(json_decode($json,true) as $row)$retained[(int)$row['id']]=true;
                foreach(json_decode($raw['items_json'],true) as $row)if(!isset($retained[(int)$row['id']]))$this->checked($wpdb->update($paid,['reimbursed'=>0,'paid_cents'=>null,'updated_at'=>current_time('mysql',true),'updated_by'=>$user],['entry_id'=>(int)$row['id']]));
                if($wpdb->delete($table,['id'=>$id,'society_code'=>$society])!==1)throw new RuntimeException('Eliminazione non riuscita.');
                $this->checked($wpdb->query('COMMIT'));return ['success'=>true];
            }catch(Throwable $e){$wpdb->query('ROLLBACK');throw $e;}
        }finally{delete_option('fr_issue_lock');}
    }
    public function revise(int $user,int $id,array $input): array {
        global $wpdb;$table=$this->table('emissioni');$paid=$this->table('rimborsi');$society=$this->society();
        if(!add_option('fr_issue_lock',time(),'',false))throw new RuntimeException('Un rimborso è già in aggiornamento.');
        try{
            $this->checked($wpdb->query('START TRANSACTION'));
            try{
                $raw=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND society_code=%s",$id,$society),ARRAY_A);
                if(!$raw)throw new RuntimeException('Rimborso non disponibile.');
                $oldOptions=json_decode($raw['options_json'],true);$revision=(int)($oldOptions['revision']??0);
                if(($input['revision']??null)!==$revision)throw new RuntimeException('Il rimborso è stato modificato da un altro operatore. Riaprilo prima di salvare.');
                $oldRows=json_decode($raw['items_json'],true);$oldBundle=FR_Refund_Documents::bundle($oldRows,$oldOptions,$society);foreach($oldRows as &$oldRow)if(empty($oldRow['receipt_id']))$oldRow['receipt_id']=$oldBundle['entry_receipts'][(string)$oldRow['id']]??'';unset($oldRow);$oldMap=[];foreach($oldRows as $row)$oldMap[(int)$row['id']]=$row;
                $ids=$input['ids']??[];if(!is_array($ids)||!$ids||count($ids)>1000||count(array_unique($ids))!==count($ids))throw new RuntimeException('Selezione non valida.');
                $rows=[];$notes=$input['line_notes']??[];
                foreach($ids as $entryId){
                    if(!is_int($entryId)||$entryId<1)throw new RuntimeException('Iscrizione non valida.');
                    if(isset($oldMap[$entryId])){$row=$oldMap[$entryId];$row['price_cents']=(int)($row['paid_cents']??$row['price_cents']);}
                    else{$row=$this->data->refund_preview([$entryId])['items'][0];if((int)$row['reimbursed']||$row['price_cents']===null)throw new RuntimeException('Iscrizione già gestita o senza prezzo.');if(($input['prices'][(string)$entryId]??null)!==(int)$row['price_cents'])throw new RuntimeException('Quota cambiata: aggiorna la selezione.');}
                    $note=$notes[(string)$entryId]??$row['refund_notes']??'';if(!is_string($note)||mb_strlen($note)>1000)throw new RuntimeException('Note riga troppo lunghe.');$row['refund_notes']=sanitize_textarea_field($note);$rows[]=$row;
                }
                $options=$input['options']??[];$date=$options['date']??'';$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
                if(!$parsed||$parsed->format('Y-m-d')!==$date||!in_array($options['allocation']??'',['half','first','second'],true)||!is_string($options['notes']??null)||mb_strlen($options['notes'])>2000)throw new RuntimeException('Impostazioni rimborso non valide.');
                $options['issuer']=$oldOptions['issuer'];$options['signature']=$oldOptions['signature']??null;$options['stamp']=$oldOptions['stamp']??null;$options['format']='total';
                $newRows=array_values(array_filter($rows,fn($r)=>!isset($oldMap[(int)$r['id']])));$newDocs=FR_Refund_Documents::capture($newRows,$society);$newMap=[];foreach($newRows as $row)$newMap[$row['id']]=$row['receipt_id'];foreach($rows as &$row)if(isset($newMap[$row['id']]))$row['receipt_id']=$newMap[$row['id']];unset($row);
                $bundle=FR_Refund_Documents::bundle($oldRows,$oldOptions,$society);$wanted=array_column($rows,'receipt_id');$docs=[];foreach(array_merge($bundle['documents'],$newDocs) as $doc)if(in_array($doc['id'],$wanted,true))$docs[$doc['id']]=$doc;$options['original_receipts']=array_values($docs);
                $options['revision']=$revision+1;$options['updated_at']=gmdate(DATE_ATOM);$options['updated_by']=$user;$options['updated_by_name']=$this->operator_name($user);
                // Keep previous versions as complete immutable snapshots, without recursive history.
                $history=$oldOptions['history']??[];unset($oldOptions['history']);$history[]=['revision'=>$revision,'items'=>$oldRows,'options'=>$oldOptions,'total_cents'=>(int)$raw['total_cents'],'replaced_at'=>gmdate(DATE_ATOM),'replaced_by'=>$user,'replaced_by_name'=>$this->operator_name($user)];$options['history']=$history;
                $total=FR_Refund_Amounts::calculate($rows,$options);$now=current_time('mysql',true);
                foreach($oldMap as $entryId=>$row)if(!in_array($entryId,$ids,true))$this->checked($wpdb->update($paid,['reimbursed'=>0,'paid_cents'=>null,'updated_at'=>$now,'updated_by'=>$user],['entry_id'=>$entryId]));
                foreach($rows as &$row){$amount=(int)($row['paid_cents']??$row['price_cents']);$record=['reimbursed'=>1,'paid_cents'=>$amount,'updated_at'=>$now,'updated_by'=>$user];$exists=$wpdb->get_var($wpdb->prepare("SELECT entry_id FROM $paid WHERE entry_id=%d",$row['id']));if($exists)$this->checked($wpdb->update($paid,$record,['entry_id'=>$row['id']]));else $this->checked($wpdb->insert($paid,['entry_id'=>$row['id']]+$record));$row['paid_cents']=$amount;$row['reimbursed']=1;}unset($row);
                $cart=$this->table('carrello');foreach($ids as $entryId)$this->checked($wpdb->delete($cart,['society_code'=>$society,'entry_id'=>$entryId]));
                $this->checked($wpdb->update($table,['items_json'=>wp_json_encode($rows),'options_json'=>wp_json_encode($options),'total_cents'=>$total],['id'=>$id]));$this->checked($wpdb->query('COMMIT'));
                if(!$wpdb->get_var($wpdb->prepare("SELECT entry_id FROM $cart WHERE society_code=%s LIMIT 1",$society))){
                    $draftKey=$this->draft_key();$draftLock=$draftKey.'_lock';
                    if(add_option($draftLock,time(),'',false)){try{$stored=$this->draft_options();update_option($draftKey,['revision'=>$stored['revision']+1,'options'=>null],false);}finally{delete_option($draftLock);}}
                }
                return $this->emission($id);
            }catch(Throwable $error){$wpdb->query('ROLLBACK');throw $error;}
        }finally{delete_option('fr_issue_lock');}
    }

    public function issue(int $user,array $input): array {
        global $wpdb;$society=$this->society();$emissions=$this->table('emissioni');$cart=$this->table('carrello');$paid=FR_Data_Repository::table('rimborsi');
        $key=$input['request_key']??'';$options=$input['options']??[];$ids=$input['ids'];
        if(!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Identificativo emissione non valido.');
        unset($options['history'],$options['revision'],$options['updated_at'],$options['updated_by'],$options['settlement']);
        $options['issuer']=trim((string)FR_Settings::all()['society_name']);$options['format']='total';
        $date=$options['date']??'';$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!$parsed||$parsed->format('Y-m-d')!==$date||!is_string($options['issuer']??null)||trim($options['issuer'])===''||mb_strlen($options['issuer'])>180
            ||!is_string($options['notes']??null)||mb_strlen($options['notes'])>2000
            ||!in_array($options['allocation']??'',['half','first','second'],true)||!in_array($options['format']??'',['total','athletes','both'],true))throw new RuntimeException('Impostazioni ricevuta non valide.');
        // Serialize issuance across users; expired locks can be recovered after interrupted requests.
        $lock='fr_issue_lock';$old=get_option($lock);
        if($old&&time()-(int)$old>120)delete_option($lock);
        if(!add_option($lock,time(),'',false))throw new RuntimeException('Un’emissione è già in corso. Riprova tra poco.');
        try{
            $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM $emissions WHERE user_id=%d AND society_code=%s AND request_key=%s",$user,$society,$key));
            if($existing)return $this->emission((int)$existing);
            $signature=FR_Signature::snapshot();
            if(array_key_exists('signature',$options)&&($options['signature']['sha256']??null)!==($signature['sha256']??null))throw new RuntimeException('La firma è cambiata: ripeti l’emissione per aggiornare le ricevute.');
            $options['signature']=$signature;
            $stamp=FR_Signature::snapshot('stamp');
            if(array_key_exists('stamp',$options)&&($options['stamp']['sha256']??null)!==($stamp['sha256']??null))throw new RuntimeException('Il timbro è cambiato: ripeti l’emissione per aggiornare le ricevute.');
            $options['stamp']=$stamp;
            $this->checked($wpdb->query('START TRANSACTION'));
            try{
                $cart_ids=array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT entry_id FROM $cart WHERE society_code=%s",$society)));
                if(array_diff($ids,$cart_ids))throw new RuntimeException('La selezione non è più nella bozza rimborso condivisa. Aggiorna la pagina.');
                $line_notes=[];foreach($this->cart_records() as $line)$line_notes[(int)$line['entry_id']]=$line['notes']??'';
                $rows=$this->data->refund_preview($ids)['items'];$options['original_receipts']=FR_Refund_Documents::capture($rows,$society);$total=0;$now=current_time('mysql',true);
                foreach($rows as &$row){
                    $id=(int)$row['id'];$amount=$row['price_cents'];$row['refund_notes']=$line_notes[$id]??'';
                    if((int)$row['reimbursed']||$amount===null)throw new RuntimeException('Quote già rimborsate o prive di prezzo: verifica la bozza rimborso.');
                    if(!isset($input['prices'][(string)$id])||!is_int($input['prices'][(string)$id])||$input['prices'][(string)$id] !== (int)$amount)throw new RuntimeException('I prezzi sono cambiati: controlla il nuovo recap.');
                    $record=['reimbursed'=>1,'paid_cents'=>(int)$amount,'updated_at'=>$now,'updated_by'=>$user];
                    $exists=$wpdb->get_var($wpdb->prepare("SELECT entry_id FROM $paid WHERE entry_id=%d",$id));
                    if($exists){$changed=$wpdb->update($paid,$record,['entry_id'=>$id,'reimbursed'=>0]);if($changed!==1)throw new RuntimeException('Quota modificata da un’altra operazione.');}
                    else{$this->checked($wpdb->insert($paid,array_merge(['entry_id'=>$id],$record)));}
                    $total+=(int)$amount;$row['price_cents']=(int)$amount;$row['paid_cents']=(int)$amount;$row['reimbursed']=1;
                }unset($row);
                $total=FR_Refund_Amounts::calculate($rows,$options);
                $this->checked($wpdb->insert($emissions,['user_id'=>$user,'society_code'=>$society,'request_key'=>$key,'created_at'=>$now,'total_cents'=>$total,'items_json'=>wp_json_encode($rows),'options_json'=>wp_json_encode($options)]));
                $id=(int)$wpdb->insert_id;
                foreach($ids as $entry_id)$this->checked($wpdb->delete($cart,['society_code'=>$society,'entry_id'=>$entry_id]));
                $this->checked($wpdb->query('COMMIT'));
                if(!$wpdb->get_var($wpdb->prepare("SELECT entry_id FROM $cart WHERE society_code=%s LIMIT 1",$society))){
                    $draftKey=$this->draft_key();$draftLock=$draftKey.'_lock';
                    if(add_option($draftLock,time(),'',false)){try{$stored=$this->draft_options();update_option($draftKey,['revision'=>$stored['revision']+1,'options'=>null],false);}finally{delete_option($draftLock);}}
                }
                return $this->emission($id);
            }catch(Throwable $error){$wpdb->query('ROLLBACK');throw $error;}
        }finally{delete_option($lock);}
    }
}
