<?php
defined('ABSPATH') || exit;

/** Portable data-only backup. Never imports SQL, sessions, password hashes or encrypted credentials. */
final class FR_Backup {
    public const TABLES=['gare','iscrizioni','prezzi','rimborsi','carrello','emissioni'];
    private const MAX_RAW=64*1024*1024;
    private const MAX_FILE=16*1024*1024;
    public static function table(string $name): string {global $wpdb;if(!in_array($name,self::TABLES,true))throw new RuntimeException('Tabella backup non valida.');return $wpdb->prefix.'fitet_ricevute_'.$name;}
    private static function check($value): void {if($value===false)throw new RuntimeException('Ripristino non riuscito. Nessuna modifica parziale salvata.');}
    private static function filename(string $name): bool {return strlen($name)<=255&&preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*\.pdf$/D',$name)&&!str_contains($name,'..');}
    private static function schemas(): array {
        preg_match_all('/CREATE TABLE \{prefix\}fitet_ricevute_(\w+) \((.*?)\) ENGINE/s',file_get_contents(FITET_RICEVUTE_DIR.'docs/schema.sql'),$matches,PREG_SET_ORDER);$schemas=[];
        foreach($matches as $match){preg_match_all('/^\s{4}([a-z_][a-z0-9_]*)\s+([^,\r\n]+)/m',$match[2],$fields,PREG_SET_ORDER);foreach($fields as $field)$schemas[$match[1]][$field[1]]=$field[2];}
        return $schemas;
    }
    public static function export(): array {
        global $wpdb;$data=['format'=>'fitet-ricevute-backup','version'=>1,'plugin_version'=>FITET_RICEVUTE_VERSION,'created_at'=>gmdate(DATE_ATOM),'tables'=>[],'users'=>[],'settings'=>FR_Settings::all(),'images'=>['signature'=>FR_Signature::snapshot(),'stamp'=>FR_Signature::snapshot('stamp')],'last_sync'=>[],'pdfs'=>[],'missing_pdfs'=>[]];
        self::check($wpdb->query('START TRANSACTION'));
        try{
            foreach(self::TABLES as $name){$table=self::table($name);$data['tables'][$name]=$wpdb->get_results("SELECT * FROM $table",ARRAY_A);if($wpdb->last_error)throw new RuntimeException('Lettura dati non riuscita.');}
            $data['users']=(new FR_Repository())->users();self::check($wpdb->query('COMMIT'));
        }catch(Throwable $error){$wpdb->query('ROLLBACK');throw $error;}
        $keys=$wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'fr_last_sync_%'");
        foreach($keys as $key)if(preg_match('/^fr_last_sync_[a-f0-9]{64}$/D',$key))$data['last_sync'][$key]=get_option($key);
        $uploads=wp_upload_dir();$directory=$uploads['basedir'].'/fitet-ricevute/ricevute-pdf';
        $names=array_unique(array_filter(array_column($data['tables']['gare'],'pdf_file')));
        if(is_dir($directory))foreach(scandir($directory) as $name)if(self::filename($name))$names[]=$name;
        $total=0;
        foreach(array_unique($names) as $name){
            if(!self::filename($name))throw new RuntimeException('Riferimento PDF non valido nel database.');
            $path=$directory.'/'.$name;
            if(!is_file($path)||is_link($path)){$data['missing_pdfs'][]=$name;continue;}
            if(filesize($path)>3*1024*1024)throw new RuntimeException('Un PDF supera il limite di 3 MB.');
            $bytes=file_get_contents($path);if($bytes===false||!str_starts_with($bytes,'%PDF-'))throw new RuntimeException('Un file PDF archiviato non è valido.');
            $total+=strlen($bytes);if($total>32*1024*1024)throw new RuntimeException('I PDF superano 32 MB: backup troppo grande per questa procedura.');
            $data['pdfs'][$name]=['sha256'=>hash('sha256',$bytes),'base64'=>base64_encode($bytes)];
        }
        return $data;
    }
    public static function encode(array $data): string {
        $json=wp_json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if($json===false||strlen($json)>self::MAX_RAW)throw new RuntimeException('Backup troppo grande (massimo 64 MB non compressi).');
        $gzip=gzencode($json,6);if($gzip===false||strlen($gzip)>self::MAX_FILE)throw new RuntimeException('Backup compresso troppo grande (massimo 16 MB).');return $gzip;
    }
    private static function defaults(array $data): array {
        if(is_array($data['tables']['gare']??null))foreach($data['tables']['gare'] as &$row)if(is_array($row))$row+=['poster_ref'=>'','poster_year'=>''];unset($row);return $data;
    }
    public static function decode(string $bytes): array {
        if(strlen($bytes)>self::MAX_FILE)throw new RuntimeException('Backup troppo grande (massimo 16 MB compressi).');
        $json=@gzdecode($bytes,self::MAX_RAW+1);if($json===false||strlen($json)>self::MAX_RAW)throw new RuntimeException('Backup compresso non valido o troppo grande.');
        try{$data=json_decode($json,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $error){throw new RuntimeException('Il backup non contiene JSON valido.');}
        if(!is_array($data))throw new RuntimeException('Formato backup non valido.'); $data=self::defaults($data);self::validate($data);return $data;
    }
    private static function json(string $value): array {try{$data=json_decode($value,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('Campo JSON del backup non valido.');}if(!is_array($data))throw new RuntimeException('Dati strutturati del backup non validi.');return $data;}
    public static function validate(array $data): void {
        if(($data['format']??'')!=='fitet-ricevute-backup'||($data['version']??0)!==1||!is_array($data['tables']??null)||array_diff(array_keys($data),['format','version','plugin_version','created_at','tables','users','settings','images','last_sync','pdfs','missing_pdfs']))throw new RuntimeException('Formato/versione del backup non supportati.');
        if(array_diff(array_keys($data['tables']),self::TABLES)||array_diff(self::TABLES,array_keys($data['tables'])))throw new RuntimeException('Tabelle del backup incomplete o non consentite.');
        if(!is_array($data['settings']??null)||array_diff(array_keys($data['settings']),array_keys(FR_Settings::defaults())))throw new RuntimeException('Impostazioni backup non consentite.');
        foreach($data['settings'] as $value)if(!is_scalar($value))throw new RuntimeException('Impostazioni backup non valide.');
        if(!is_array($data['users']??null)||!array_is_list($data['users']))throw new RuntimeException('Utenti del backup non validi.');
        $users=[];$usernames=[];
        foreach($data['users'] as $u){
            if(!is_array($u)||array_diff(array_keys($u),['id','username','display_name','enabled','created_at'])||empty($u['id'])||!ctype_digit((string)$u['id'])||!is_string($u['username']??null)||!preg_match('/^[a-z0-9][a-z0-9._-]{2,59}$/D',$u['username'])||!is_string($u['display_name']??null)||$u['display_name']===''||mb_strlen($u['display_name'])>120||isset($users[$u['id']])||isset($usernames[$u['username']]))throw new RuntimeException('Identità degli utenti nel backup non valide.');
            $users[$u['id']]=$u;$usernames[$u['username']]=true;
        }
        $schemas=self::schemas();$indexes=[];$unique=[];$total=0;
        $keys=['gare'=>['id'],'iscrizioni'=>['id'],'prezzi'=>['race_id'],'rimborsi'=>['entry_id'],'carrello'=>['user_id','society_code','entry_id'],'emissioni'=>['id']];
        $natural=['gare'=>['society_code','tournament_key','race_key'],'iscrizioni'=>['race_id','entry_key'],'emissioni'=>['user_id','society_code','request_key']];
        foreach(self::TABLES as $name){
            $rows=$data['tables'][$name];if(!is_array($rows)||!array_is_list($rows)||($total+=count($rows))>100000)throw new RuntimeException('Numero di righe del backup non valido o oltre 100000.');
            foreach($rows as $row){
                if(!is_array($row)||array_diff(array_keys($row),array_keys($schemas[$name]))||array_diff(array_keys($schemas[$name]),array_keys($row)))throw new RuntimeException('Colonne del backup non valide: '.$name.'.');
                foreach($row as $field=>$value){
                    $type=$schemas[$name][$field];
                    if($value===null){if(str_contains($type,'NOT NULL'))throw new RuntimeException('Valore obbligatorio mancante nel backup.');continue;}
                    if(!is_scalar($value))throw new RuntimeException('Valore del backup non scalare.');
                    if(preg_match('/^(?:bigint|int|tinyint)/',$type)){
                        if(!preg_match('/^\d+$/D',(string)$value)||strlen((string)$value)>18||((str_starts_with($type,'tinyint'))&&(int)$value>1))throw new RuntimeException('Valore numerico nel backup non valido.');
                    }else{
                        if(!is_string($value))throw new RuntimeException('Testo nel backup non valido.');
                        if(preg_match('/^(?:var)?char\((\d+)\)/',$type,$match)&&mb_strlen($value)>(int)$match[1])throw new RuntimeException('Testo nel backup troppo lungo.');
                        if(str_ends_with($field,'_json')||$field==='source_query')self::json($value);
                    }
                    if(in_array($field,['user_id','updated_by','manual_updated_by'],true)&&(int)$value>0&&!isset($users[$value]))throw new RuntimeException('Operatore referenziato ma assente nel backup.');
                }
                $key=implode('|',array_map(fn($key)=>(string)$row[$key],$keys[$name]));
                if(isset($indexes[$name][$key]))throw new RuntimeException('Righe duplicate nel backup.');$indexes[$name][$key]=$row;
                if(isset($natural[$name])){$key=implode('|',array_map(fn($key)=>(string)$row[$key],$natural[$name]));if(isset($unique[$name][$key]))throw new RuntimeException('Identità duplicate nel backup.');$unique[$name][$key]=true;}
                if($name==='gare'&&$row['pdf_file']!==''&&!self::filename($row['pdf_file']))throw new RuntimeException('Nome PDF non sicuro nel backup.');
                if($name==='emissioni'){
                    $items=self::json($row['items_json']);$options=self::json($row['options_json']);$sum=0;
                    if(!$items||!array_is_list($items))throw new RuntimeException('Emissione senza iscrizioni nel backup.');
                    foreach($items as $item){if(!isset($item['id'],$item['race_id'],$item['price_cents'])||!is_numeric($item['price_cents'])||(int)$item['price_cents']<0||($item['society_code']??'')!==$row['society_code'])throw new RuntimeException('Snapshot emissione non valido.');$sum+=(int)$item['price_cents'];}
                    if($sum!==(int)$row['total_cents'])throw new RuntimeException('Totale emissione incoerente nel backup.');
                    foreach(['signature','stamp'] as $kind)FR_Signature::validate_snapshot($options[$kind]??null);
                }
            }
        }
        foreach($data['tables']['iscrizioni'] as $row)if(!isset($indexes['gare'][$row['race_id']]))throw new RuntimeException('Iscrizione senza gara nel backup.');
        foreach($data['tables']['prezzi'] as $row)if(!isset($indexes['gare'][$row['race_id']]))throw new RuntimeException('Prezzo senza gara nel backup.');
        foreach($data['tables']['rimborsi'] as $row)if(!isset($indexes['iscrizioni'][$row['entry_id']]))throw new RuntimeException('Rimborso senza iscrizione nel backup.');
        foreach($data['tables']['carrello'] as $row){$entry=$indexes['iscrizioni'][$row['entry_id']]??null;$race=$entry?($indexes['gare'][$entry['race_id']]??null):null;if(!$race||$race['society_code']!==$row['society_code'])throw new RuntimeException('Bozza rimborso incoerente nel backup.');}
        if(!is_array($data['images']??null)||array_diff(array_keys($data['images']),['signature','stamp']))throw new RuntimeException('Immagini backup non valide.');
        foreach(['signature','stamp'] as $kind)FR_Signature::validate_snapshot($data['images'][$kind]??null);
        if(!is_array($data['pdfs']??null)||!is_array($data['last_sync']??null))throw new RuntimeException('File o date backup non validi.');
        foreach($data['last_sync'] as $key=>$value)if(!preg_match('/^fr_last_sync_[a-f0-9]{64}$/D',$key)||!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}Z| \d{2}:\d{2}:\d{2})$/D',$value))throw new RuntimeException('Data di sincronizzazione nel backup non valida.');
        $size=0;
        foreach($data['pdfs'] as $name=>$file){
            if(!self::filename((string)$name)||!is_array($file)||!is_string($file['base64']??null)||!is_string($file['sha256']??null))throw new RuntimeException('File nel backup non consentito.');
            $bytes=base64_decode($file['base64'],true);
            if($bytes===false||strlen($bytes)>3*1024*1024||!str_starts_with($bytes,'%PDF-')||hash('sha256',$bytes)!==$file['sha256'])throw new RuntimeException('PDF nel backup non integro.');
            if(($size+=strlen($bytes))>32*1024*1024)throw new RuntimeException('PDF nel backup oltre il limite.');
        }
        foreach($data['tables']['gare'] as $row)if(isset($data['pdfs'][$row['pdf_file']])&&$row['pdf_sha256']!==$data['pdfs'][$row['pdf_file']]['sha256'])throw new RuntimeException('Hash del PDF diverso dalla gara associata.');
    }
    public static function summary(array $data): array {
        $result=[];foreach(self::TABLES as $name)$result[$name]=count($data['tables'][$name]);$result['pdf']=count($data['pdfs']);$result['pdf_mancanti']=count(array_filter(array_unique(array_column($data['tables']['gare'],'pdf_file')),fn($name)=>!isset($data['pdfs'][$name])));return $result;
    }
    public static function restore(array $data): void {
        global $wpdb;$data=self::defaults($data);self::validate($data);$lock='fr_backup_restore_lock';$old=get_option($lock);if($old&&time()-(int)$old>300)delete_option($lock);
        if(!add_option($lock,time(),'',false))throw new RuntimeException('Un ripristino è già in corso.');
        $created=[];$option_keys=[FR_Settings::OPTION,FR_Signature::OPTION,FR_Signature::STAMP_OPTION,'fitet_ricevute_data_generation'];
        try{
            $uploads=wp_upload_dir();if($uploads['error'])throw new RuntimeException('Cartella uploads non disponibile.');
            $directory=$uploads['basedir'].'/fitet-ricevute/ricevute-pdf';
            if(!wp_mkdir_p($directory)||is_link($directory))throw new RuntimeException('Cartella PDF non utilizzabile.');
            foreach(['.htaccess'=>"Require all denied\n",'index.html'=>'','web.config'=>'<configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>'] as $name=>$body)if(!is_file($directory.'/'.$name)&&file_put_contents($directory.'/'.$name,$body)===false)throw new RuntimeException('Protezione cartella PDF non riuscita.');
            foreach($data['pdfs'] as $name=>$file){$path=$directory.'/'.$name;if(is_link($path))throw new RuntimeException('Destinazione PDF non consentita.');if(is_file($path)){if(hash_file('sha256',$path)!==$file['sha256'])throw new RuntimeException('Un PDF locale con lo stesso nome ha contenuto diverso. Nessuna sovrascrittura.');continue;}$bytes=base64_decode($file['base64'],true);$created[]=$path;if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('Scrittura PDF non riuscita.');}
            self::check($wpdb->query('START TRANSACTION'));
            try{
                $map=[0=>0];$repo=new FR_Repository();
                foreach($data['users'] as $user){$existing=$repo->user_by_username($user['username']);if($existing)$map[(int)$user['id']]=(int)$existing['id'];else{$id=$repo->save_user(0,['username'=>$user['username'],'display_name'=>$user['display_name'],'password'=>wp_generate_password(64,true,true),'enabled'=>false]);if(is_wp_error($id))throw new RuntimeException($id->get_error_message());$map[(int)$user['id']]=(int)$id;}}
                foreach(array_reverse(self::TABLES) as $name){$table=self::table($name);self::check($wpdb->query("DELETE FROM $table"));}
                foreach(self::TABLES as $name)foreach($data['tables'][$name] as $row){foreach(['user_id','updated_by','manual_updated_by'] as $field)if(isset($row[$field]))$row[$field]=$map[(int)$row[$field]];self::check($wpdb->insert(self::table($name),$row));}
                $settings=FR_Settings::sanitize($data['settings']);update_option(FR_Settings::OPTION,$settings,false);if(FR_Settings::all()!==$settings)throw new RuntimeException('Ripristino impostazioni non riuscito.');
                FR_Signature::replace(FR_Signature::validate_snapshot($data['images']['signature']??null));FR_Signature::replace(FR_Signature::validate_snapshot($data['images']['stamp']??null),'stamp');
                $keys=$wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'fr_last_sync_%'");foreach($keys as $key)if(preg_match('/^fr_last_sync_[a-f0-9]{64}$/D',$key)){delete_option($key);$option_keys[]=$key;}
                foreach($data['last_sync'] as $key=>$value){update_option($key,$value,false);$option_keys[]=$key;if(get_option($key)!==$value)throw new RuntimeException('Ripristino data non riuscito.');}
                update_option('fitet_ricevute_data_generation',bin2hex(random_bytes(16)),false);
                $sessions=FR_Repository::sessions_table();self::check($wpdb->query("DELETE FROM $sessions"));
                self::check($wpdb->query('COMMIT'));
            }catch(Throwable $error){$wpdb->query('ROLLBACK');foreach($option_keys as $key)wp_cache_delete($key,'options');wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');throw $error;}
        }catch(Throwable $error){foreach($created as $path)if(is_file($path))unlink($path);throw $error;}
        finally{delete_option($lock);}
    }
}
