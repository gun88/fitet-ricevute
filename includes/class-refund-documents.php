<?php
defined('ABSPATH') || exit;
final class FR_Refund_Documents {
    public static function capture(array &$items,string $society): array {
        global $wpdb;$ids=array_values(array_unique(array_map('intval',array_column($items,'race_id'))));$races=[];
        if($ids){$table=FR_Data_Repository::table('gare');$marks=implode(',',array_fill(0,count($ids),'%d'));$rows=$wpdb->get_results($wpdb->prepare("SELECT id,tournament_key,pdf_file,pdf_sha256,source_url FROM $table WHERE society_code=%s AND id IN ($marks)",array_merge([$society],$ids)),ARRAY_A);if($wpdb->last_error)throw new RuntimeException('Lettura ricevute originali non riuscita.');foreach($rows as $r)$races[(int)$r['id']]=$r;}
        $documents=[];
        foreach($items as &$item){
            $race=$races[(int)$item['race_id']]??[];if(($race['tournament_key']??null)!==$item['tournament_key'])$race=[];
            $sha=(string)($race['pdf_sha256']??'');$file=(string)($race['pdf_file']??'');$id=preg_match('/^[a-f0-9]{64}$/D',$sha)?$sha:hash('sha256','missing:'.$item['tournament_key']);
            $item['receipt_id']=$id;
            if(!isset($documents[$id]))$documents[$id]=['id'=>$id,'file'=>$file,'sha256'=>$sha,'source_url'=>(string)($race['source_url']??''),'tournament_name'=>$item['tournament_name'],'tournament_date'=>$item['tournament_date'],'tournament_key'=>$item['tournament_key']];
        }unset($item);return array_values($documents);
    }
    private static function path(array $document): ?string {
        $name=$document['file']??'';if(!is_string($name)||strlen($name)>255||!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*\.pdf$/D',$name)||str_contains($name,'..'))return null;
        $uploads=wp_upload_dir();if($uploads['error'])return null;$directory=$uploads['basedir'].'/fitet-ricevute/ricevute-pdf';if(is_link($directory))return null;
        $base=realpath($directory);$path=$directory.'/'.$name;if(!$base||is_link($path)||!is_file($path)||!is_readable($path))return null;$real=realpath($path);
        return $real&&str_starts_with($real,$base.DIRECTORY_SEPARATOR)&&filesize($real)<=3*1024*1024?$real:null;
    }
    public static function read(array $document): string {
        $path=self::path($document);if(!$path)throw new RuntimeException('Ricevuta originale non disponibile nell’archivio.');$bytes=file_get_contents($path);
        if($bytes===false||!str_starts_with($bytes,'%PDF-')||!preg_match('/^[a-f0-9]{64}$/D',$document['sha256']??'')||hash('sha256',$bytes)!==$document['sha256'])throw new RuntimeException('Ricevuta originale non integra: impossibile completare il PDF.');return $bytes;
    }
    public static function bundle(array $items,array $options,string $society): array {
        $legacy=!array_key_exists('original_receipts',$options);$documents=$legacy?self::capture($items,$society):$options['original_receipts'];$mapping=[];
        foreach($items as $item)$mapping[(string)$item['id']]=$item['receipt_id']??'';
        return ['documents'=>$documents,'entry_receipts'=>$mapping,'legacy'=>$legacy];
    }
    public static function manifest(array $bundle): array {
        $items=[];foreach($bundle['documents'] as $index=>$doc){$item=$doc;unset($item['file']);$item['reference']='R'.str_pad((string)($index+1),2,'0',STR_PAD_LEFT);$item['available']=self::path($doc)!==null;$items[]=$item;}
        return ['items'=>$items,'entry_receipts'=>$bundle['entry_receipts'],'legacy'=>$bundle['legacy']];
    }
}
