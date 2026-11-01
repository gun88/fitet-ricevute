<?php
defined('ABSPATH') || exit;
/** Locandine remain external: only HTML is read and a validated filename reference is stored. */
final class FR_Tournament_Poster {
    private const ROOT='https://portale.fitet.org/risultati/tornei/tabelloni/';
    public static function current(string $date): bool {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))return false;
        $season=fn($d)=>(int)substr($d,0,4)-((int)substr($d,5,2)<7?1:0);
        return $season($date)===$season(wp_date('Y-m-d'));
    }
    public static function valid_ref(string $ref,string $committee,string $number): bool {
        return preg_match('/^(\d+)_(\d+)_(\d+)$/D',$ref,$m)&&ltrim($m[1],'0')===ltrim($committee,'0')&&ltrim($m[2],'0')===ltrim($number,'0')&&strlen($ref)<=100;
    }
    public static function url(array $row): ?string {
        $committee=(string)($row['source_committee']??'');$number=(string)($row['source_tournament']??'');$ref=(string)($row['poster_ref']??'');
        if($committee===''||(ltrim($committee,'0')?:'0')==='30'||!self::valid_ref($ref,$committee,$number))return null;
        if(self::current((string)($row['tournament_date']??'')))return self::ROOT.$ref.'.pdf';
        $year=(string)(($row['poster_year']??'')?:($row['source_year']??''));
        return ctype_digit($year)&&$year!==''?self::ROOT.$year.'/'.$ref.'.pdf':null;
    }
    public static function extract(string $html,string $base,array $row): ?array {
        if(!mb_check_encoding($html,'UTF-8'))$html=mb_convert_encoding($html,'UTF-8','Windows-1252');
        $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);
        $current=self::current($row['tournament_date']);$expected=(string)(($row['source_year']??'')?:($row['poster_year']??''));$refs=[];$years=[];
        foreach($dom->getElementsByTagName('a') as $a){
            $url=WP_Http::make_absolute_url(html_entity_decode($a->getAttribute('href'),ENT_QUOTES|ENT_HTML5,'UTF-8'),$base);$parts=wp_parse_url($url);
            if(!in_array($parts['scheme']??'',['http','https'],true)||strtolower($parts['host']??'')!=='portale.fitet.org'||isset($parts['port'])||isset($parts['user'])||isset($parts['pass']))continue;
            $path=$parts['path']??'';
            if(preg_match('~^/risultati/tornei/tabelloni/(?:(\d+)/)?(\d+_\d+_\d+)\.pdf$~D',$path,$m)&&!isset($parts['query'])&&self::valid_ref($m[2],$row['source_committee'],$row['source_tournament'])){
                $year=$m[1];if(($current&&$year!=='')||(!$current&&($expected===''||$year!==$expected)))continue;
                $refs[$m[2]]=$year;
            }
            // Observe federation season IDs; never infer ANNO from the calendar year.
            if($current&&$path==='/risultati/tornei/visualizza.php'){
                parse_str($parts['query']??'',$params);
                if((string)($params['IDT']??'')===$row['source_tournament']&&(string)($params['COM']??'')===$row['source_committee']&&is_string($params['ANNO']??null)&&ctype_digit($params['ANNO']))$years[$params['ANNO']]=true;
            }
        }
        if(count($refs)!==1)return null;
        $ref=array_key_first($refs);$year=$refs[$ref];
        if($current){if(count($years)>1)return null;$observed=(string)(array_key_first($years)??'');if($expected!==''&&$observed!==''&&$expected!==$observed)return null;$year=$observed?:$expected;}
        return ($current||$year!=='')?['poster_ref'=>$ref,'poster_year'=>$year]:null;
    }
    public static function discover(array $row): ?array {
        foreach(['source_committee','source_tournament'] as $key)if(!is_string($row[$key]??null)||!ctype_digit($row[$key]))return null;
        if((ltrim($row['source_committee'],'0')?:'0')==='30')return null;
        if(self::current($row['tournament_date']))$url='https://portale.fitet.org/risultati/tornei/elenco_gare.php?'.http_build_query(['IDT'=>$row['source_tournament'],'COM'=>$row['source_committee']]);
        else{$year=(string)(($row['source_year']??'')?:($row['poster_year']??''));if($year===''||!ctype_digit($year))return null;$url='https://portale.fitet.org/risultati/archivio/elenco_gare_tornei.asp?'.http_build_query(['ANNO'=>$year,'COM'=>$row['source_committee'],'IDT'=>$row['source_tournament']]);}
        $response=wp_safe_remote_get($url,['cookies'=>[],'timeout'=>8,'redirection'=>0,'limit_response_size'=>1024*1024+1,'headers'=>['Accept'=>'text/html','User-Agent'=>'FITET Ricevute/'.FITET_RICEVUTE_VERSION]]);
        if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)return null;
        $html=wp_remote_retrieve_body($response);if(trim($html)===''||strlen($html)>1024*1024||str_starts_with($html,'%PDF-'))return null;
        return self::extract($html,$url,$row);
    }
}
