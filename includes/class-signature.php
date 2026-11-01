<?php
defined('ABSPATH') || exit;
final class FR_Signature {
    public const OPTION='fitet_ricevute_signature';
    public const STAMP_OPTION='fitet_ricevute_stamp';
    public static function option(string $kind): string {
        if(!in_array($kind,['signature','stamp'],true))throw new InvalidArgumentException('Tipo immagine non valido.');
        return $kind==='stamp'?self::STAMP_OPTION:self::OPTION;
    }
    public static function snapshot(string $kind='signature'): ?array {
        $value=get_option(self::option($kind),null);
        return is_array($value)&&isset($value['data_url'],$value['sha256'],$value['width'],$value['height'],$value['format'])?$value:null;
    }
    public static function validated(string $bytes): array {
        if(strlen($bytes)>1024*1024||strlen($bytes)<32)throw new RuntimeException('Carica un’immagine PNG o JPG fino a 1 MB.');
        $info=@getimagesizefromstring($bytes);
        if(!$info||!in_array($info['mime']??'',['image/png','image/jpeg'],true)||$info[0]<1||$info[1]<1||$info[0]>4096||$info[1]>4096)throw new RuntimeException('Immagine non valida: usa PNG o JPG, massimo 4096 × 4096 pixel.');
        if(function_exists('imagecreatefromstring')){
            $image=@imagecreatefromstring($bytes);if($image===false)throw new RuntimeException('Il file immagine è danneggiato.');imagedestroy($image);
        }
        return ['data_url'=>'data:'.$info['mime'].';base64,'.base64_encode($bytes),'sha256'=>hash('sha256',$bytes),'width'=>(int)$info[0],'height'=>(int)$info[1],'format'=>$info['mime']==='image/png'?'PNG':'JPEG'];
    }
    public static function validate_snapshot($value): ?array {
        if($value===null)return null;
        if(!is_array($value)||!is_string($value['data_url']??null)||!preg_match('~^data:image/(?:png|jpeg);base64,([A-Za-z0-9+/=]+)$~D',$value['data_url'],$match))throw new RuntimeException('Immagine nel backup non valida.');
        $bytes=base64_decode($match[1],true);if($bytes===false)throw new RuntimeException('Immagine nel backup non leggibile.');
        $image=self::validated($bytes);
        if(($value['sha256']??null)!==$image['sha256']||(int)($value['width']??0)!==$image['width']||(int)($value['height']??0)!==$image['height']||($value['format']??null)!==$image['format'])throw new RuntimeException('Immagine nel backup incoerente.');
        return $image;
    }
    public static function replace(?array $value,string $kind='signature'): void {
        $option=self::option($kind);
        if($value===null)delete_option($option);else update_option($option,$value,false);
        if(self::snapshot($kind)!==$value)throw new RuntimeException('Salvataggio immagine non riuscito.');
    }
    public static function prepare_upload(array $file,bool $clear=false,string $kind='signature'): ?array {
        $error=$file['error']??UPLOAD_ERR_NO_FILE;
        if($clear){if($error!==UPLOAD_ERR_NO_FILE)throw new RuntimeException('Scegli se caricare o rimuovere la stessa immagine.');return null;}
        if($error===UPLOAD_ERR_NO_FILE)return self::snapshot($kind);
        if($error!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new RuntimeException('Caricamento non riuscito. Usa PNG o JPG fino a 1 MB.');
        if(($file['size']??0)>1024*1024)throw new RuntimeException('L’immagine supera 1 MB.');
        $bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new RuntimeException('Immagine non leggibile.');return self::validated($bytes);
    }
    public static function store(string $bytes,string $kind='signature'): void {self::replace(self::validated($bytes),$kind);}
    public static function upload(array $file): void {self::replace(self::prepare_upload($file));}
    public static function remove(): void {self::replace(null);}
}
