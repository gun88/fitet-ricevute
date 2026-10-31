<?php
// Explicit CLI-only live check. Credentials are read from a supplied request file, kept in memory,
// and never written to settings, fixtures, logs, or output. No registrations are submitted.
if (PHP_SAPI !== 'cli' || empty($argv[1]) || empty($argv[2]) || !ctype_digit($argv[2])) { fwrite(STDERR, "Usage: php tests/live-discovery.php request-file society-code\n"); exit(2); }
ini_set('zend.exception_ignore_args', '1');
define('WP_ACCESSIBLE_HOSTS', 'portale.fitet.org');
$_SERVER['HTTP_HOST']='127.0.0.1:8095'; $_SERVER['REQUEST_URI']='/';
require __DIR__.'/.runtime/wordpress/wp-load.php';
remove_all_filters('pre_http_request'); // The isolated fixture must not intercept this explicitly requested live check.
try {
    $request=file_get_contents($argv[1]);
    if (!preg_match("/--data-raw '([^']+)'/s",$request,$match)) { throw new RuntimeException('Payload di accesso non trovato nel file.'); }
    parse_str($match[1],$payload);
    $credentials=['username'=>$payload['login']??'','password'=>$payload['password']??''];
    unset($request,$match,$payload);
    $links=(new FR_Extranet_Client())->discover($argv[2],$credentials);
    unset($credentials);
    file_put_contents(__DIR__.'/.runtime/live-tournament-links.json',wp_json_encode($links,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo wp_json_encode(['live_login'=>true,'catalog_tournaments'=>count($links['tournaments']),'listed_receipts'=>count($links['receipts']),'saved'=>'tests/.runtime/live-tournament-links.json'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $error) { unset($credentials);fwrite(STDERR,$error->getMessage()."\n");exit(1); }
