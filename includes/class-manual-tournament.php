<?php
defined('ABSPATH') || exit;
final class FR_Manual_Tournament {
    private static function text($value,int $max,bool $required=true): string {if(!is_string($value)||mb_strlen($value)>$max||($required&&trim($value)===''))throw new RuntimeException('Compila tutti i campi obbligatori rispettando le lunghezze indicate.');return sanitize_text_field($value);}
    private static function upload(array $file,int $kb): array {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||filesize($file['tmp_name'])>$kb*1024)throw new RuntimeException('Documento mancante o superiore al limite di '.$kb.' KB.');
        $bytes=file_get_contents($file['tmp_name']);if($bytes===false||!str_starts_with($bytes,'%PDF-')||!str_contains(substr($bytes,-2048),'%%EOF'))throw new RuntimeException('Documento PDF non valido.');
        // Images are converted to PDF on the client; limits apply to the final archived document.
        $sha=hash('sha256',$bytes);return ['file'=>'manual-'.$sha.'.pdf','sha'=>$sha,'bytes'=>$bytes];
    }
    public static function create(array $input,array $files,int $user): array {
        $lock='fr_manual_tournament_lock';$old=get_option($lock);if($old&&time()-(int)$old>120)delete_option($lock);if(!add_option($lock,time(),'',false))throw new RuntimeException('Un torneo manuale è già in salvataggio. Riprova.');
        try{return self::create_locked($input,$files,$user);}finally{delete_option($lock);}
    }
    private static function create_locked(array $input,array $files,int $user): array {
        global $wpdb;$settings=FR_Settings::all();$society=ltrim((string)$settings['society_code'],'0')?:'0';if(!$settings['society_code'])throw new RuntimeException('Configura il codice società.');
        if(($input['confirmed']??false)!==true)throw new RuntimeException('Conferma che il torneo non sia disponibile nell’extranet.');
        $name=self::text($input['name']??null,500);$place=self::text($input['place']??null,255);$committee=self::text($input['committee']??null,10);if(!ctype_digit($committee))throw new RuntimeException('Codice comitato non valido.');$committeeName=self::text($input['committee_name']??null,255);
        $date=$input['date']??'';$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new RuntimeException('Data torneo non valida.');
        $races=$input['races']??[];if(!is_array($races)||!$races||count($races)>100)throw new RuntimeException('Inserisci da 1 a 100 gare.');$clean=[];$count=0;$expected=0;
        foreach($races as $race){$raceName=self::text($race['name']??null,500);$kind=$race['kind']??'';if(!in_array($kind,['single','double'],true)||!is_int($race['cents']??null)||$race['cents']<0||$race['cents']>100000000)throw new RuntimeException('Tipo gara o quota non validi.');$entries=$race['entries']??[];if(!is_array($entries)||count($entries)>1000)throw new RuntimeException('Troppe iscrizioni.');$keys=[];
            foreach($entries as &$entry){foreach([1,2] as $i){if($i===2&&$kind==='single'){unset($entry['name2'],$entry['card2']);continue;}$entry['name'.$i]=self::text($entry['name'.$i]??null,255);$entry['card'.$i]=self::text($entry['card'.$i]??null,32);if(!ctype_digit($entry['card'.$i]))throw new RuntimeException('La tessera deve contenere solo numeri.');}
                $ids=[$entry['card1']];if($kind==='double')$ids[]=$entry['card2'];if(count(array_unique($ids))!==count($ids))throw new RuntimeException('Un doppio richiede due atleti diversi.');sort($ids);$key=implode(':',$ids);if(isset($keys[$key]))throw new RuntimeException('Iscrizione duplicata nella gara.');$keys[$key]=true;
            }unset($entry);$count+=count($entries);$expected+=count($entries)*$race['cents'];$clean[]=['name'=>$raceName,'kind'=>$kind,'cents'=>$race['cents'],'entries'=>$entries];
        }
        if($count>1000)throw new RuntimeException('Massimo 1000 iscrizioni per torneo.');$total=$input['total_cents']??$expected;if(!is_int($total)||$total<0||$total>100000000)throw new RuntimeException('Totale ricevuta non valido.');
        $receipt=self::upload($files['receipt']??[],(int)$settings['manual_receipt_kb']);$poster=isset($files['poster'])?self::upload($files['poster'],(int)$settings['manual_poster_kb']):null;
        $gare=FR_Data_Repository::table('gare');$iscrizioni=FR_Data_Repository::table('iscrizioni');$prezzi=FR_Data_Repository::table('prezzi');
        if($wpdb->get_var($wpdb->prepare("SELECT id FROM $gare WHERE society_code=%s AND tournament_date=%s AND LOWER(tournament_name)=LOWER(%s) AND source_committee=%s AND active=1",$society,$date,$name,$committee)))throw new RuntimeException('Esiste già un torneo con lo stesso nome, data e comitato.');
        $uploads=wp_upload_dir();if($uploads['error'])throw new RuntimeException('Archivio non disponibile.');$dir=$uploads['basedir'].'/fitet-ricevute/ricevute-pdf';if(is_link(dirname($dir))||is_link($dir)||!wp_mkdir_p($dir))throw new RuntimeException('Archivio non consentito.');
        $created=[];$check=static function($v){if($v===false)throw new RuntimeException('Salvataggio torneo non riuscito.');};
        try{
            foreach(['.htaccess'=>"Require all denied\n",'index.html'=>'','web.config'=>'<configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>'] as $file=>$body)if(!is_file($dir.'/'.$file)&&file_put_contents($dir.'/'.$file,$body)===false)throw new RuntimeException('Protezione archivio non riuscita.');
            foreach(array_filter([$receipt,$poster]) as $document){$path=$dir.'/'.$document['file'];if(is_link($path))throw new RuntimeException('Documento non consentito.');if(!is_file($path)){$created[]=$path;if(file_put_contents($path,$document['bytes'],LOCK_EX)!==strlen($document['bytes']))throw new RuntimeException('Scrittura documento non riuscita.');}elseif(hash_file('sha256',$path)!==$document['sha'])throw new RuntimeException('Documento archivio non integro.');}
            $check($wpdb->query('START TRANSACTION'));$key=hash('sha256','manual:'.bin2hex(random_bytes(16)));$now=current_time('mysql',true);
            foreach($clean as $n=>$race){$record=['tournament_key'=>$key,'tournament_ref'=>'manual:'.$key,'race_key'=>hash('sha256',$key.':'.$n),'society_code'=>$society,'source_year'=>'','source_committee'=>$committee,'source_tournament'=>'m'.substr($key,0,16),'source_query'=>'{}','source_url'=>'','tournament_name'=>$name,'tournament_date'=>$date,'tournament_place'=>$place,'committee_name'=>$committeeName,'society_name'=>$settings['society_name'],'race_name'=>$race['name'],'race_kind'=>$race['kind'],'receipt_total_cents'=>$total,'receipt_entries'=>$count,'pdf_file'=>$receipt['file'],'pdf_sha256'=>$receipt['sha'],'source_json'=>wp_json_encode(['manual'=>true,'created_by'=>$user,'poster'=>$poster?['file'=>$poster['file'],'sha256'=>$poster['sha']]:null]),'active'=>1,'synced_at'=>$now];$check($wpdb->insert($gare,$record));$raceId=(int)$wpdb->insert_id;
                $check($wpdb->insert($prezzi,['race_id'=>$raceId,'automatic_cents'=>null,'manual_cents'=>$race['cents'],'auto_basis'=>'manual','auto_updated_at'=>$now,'manual_updated_by'=>$user,'manual_updated_at'=>$now]));
                foreach($race['entries'] as $i=>$entry){$row=['race_id'=>$raceId,'entry_key'=>hash('sha256',wp_json_encode($entry)),'athlete1_name'=>$entry['name1'],'athlete1_card'=>$entry['card1'],'athlete2_name'=>$entry['name2']??null,'athlete2_card'=>$entry['card2']??null,'source_page'=>1,'source_row'=>$i+1,'source_json'=>wp_json_encode($entry),'active'=>1,'synced_at'=>$now];$check($wpdb->insert($iscrizioni,$row));}
            }$check($wpdb->query('COMMIT'));return ['tournament_key'=>$key];
        }catch(Throwable $e){$wpdb->query('ROLLBACK');foreach($created as $path)unlink($path);throw $e;}
    }
    public static function poster(string $key): string {
        global $wpdb;$table=FR_Data_Repository::table('gare');$society=ltrim((string)FR_Settings::all()['society_code'],'0')?:'0';$raw=$wpdb->get_var($wpdb->prepare("SELECT source_json FROM $table WHERE tournament_key=%s AND society_code=%s AND active=1 LIMIT 1",$key,$society));$data=json_decode($raw??'{}',true);if(empty($data['manual'])||empty($data['poster']))throw new RuntimeException('Locandina non disponibile.');return FR_Refund_Documents::read($data['poster']);
    }
}
