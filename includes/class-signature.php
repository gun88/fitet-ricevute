<?php
defined('ABSPATH') || exit;
final class FR_Signature {
    public const OPTION='fitet_ricevute_signature';
    public static function snapshot(): ?array {
        $value=get_option(self::OPTION,null);
        return is_array($value)&&isset($value['data_url'],$value['sha256'],$value['width'],$value['height'],$value['format'])?$value:null;
    }
    public static function store(string $bytes): void {
        if(strlen($bytes)>1024*1024||strlen($bytes)<32)throw new RuntimeException('Carica un’immagine PNG o JPG fino a 1 MB.');
        $info=@getimagesizefromstring($bytes);
        if(!$info||!in_array($info['mime']??'',['image/png','image/jpeg'],true)||$info[0]<1||$info[1]<1||$info[0]>4096||$info[1]>4096)throw new RuntimeException('Immagine non valida: usa PNG o JPG, massimo 4096 × 4096 pixel.');
        if(function_exists('imagecreatefromstring')){
            $image=@imagecreatefromstring($bytes);
            if($image===false)throw new RuntimeException('Il file immagine è danneggiato.');
            imagedestroy($image);
        }
        $value=['data_url'=>'data:'.$info['mime'].';base64,'.base64_encode($bytes),'sha256'=>hash('sha256',$bytes),'width'=>(int)$info[0],'height'=>(int)$info[1],'format'=>$info['mime']==='image/png'?'PNG':'JPEG'];
        update_option(self::OPTION,$value,false);
        if(get_option(self::OPTION)!==$value)throw new RuntimeException('Salvataggio della firma non riuscito.');
    }
    public static function upload(array $file): void {
        if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return;
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new RuntimeException('Caricamento non riuscito. Seleziona un’immagine PNG o JPG fino a 1 MB.');
        if(($file['size']??0)>1024*1024)throw new RuntimeException('La firma supera 1 MB. Riduci il file e riprova.');
        $bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new RuntimeException('Immagine non leggibile.');self::store($bytes);
    }
    public static function remove(): void { delete_option(self::OPTION); }
}
