<?php
// Loaded ONLY as a mu-plugin in tests/.runtime. No live FITeT login is performed by fixture tests.
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (!get_option('fr_test_mock_extranet') || wp_parse_url($url, PHP_URL_HOST) !== 'portale.fitet.org') { return $pre; }
    $trace = get_option('fr_test_http_trace', []);
    $trace[] = [$args['method'], $url]; update_option('fr_test_http_trace', $trace, false);
    $path = wp_parse_url($url, PHP_URL_PATH);
    parse_str(wp_parse_url($url, PHP_URL_QUERY) ?? '', $params);
    $mode = get_option('fr_test_extranet_failure', '');
    $has_cookie = fn(string $name, string $value) => (bool) array_filter($args['cookies'], fn($cookie) => $cookie->name === $name && $cookie->value === $value);
    $status = 200; $headers = []; $cookies = []; $body = '';
    if ($path === '/login/login.asp') {
        $body = '<form method="POST" action="autentica.asp?COMIT=30"><input type="hidden" name="paginaOriginale" value=""><input type="text" name="login"><input type="password" name="password"></form>';
        $cookies[] = new WP_Http_Cookie(['name'=>'ASPSESSIONIDTEST','value'=>'initial-fixture','domain'=>'portale.fitet.org','path'=>'/']);
    } elseif ($path === '/login/autentica.asp') {
        if (($args['body']['login'] ?? '') !== 'Societa.Fixture' || ($args['body']['password'] ?? '') !== 'Fixture-FITeT-2026!'
            || ($params['COMIT'] ?? '') !== '30' || !array_key_exists('paginaOriginale',$args['body'])
            || !$has_cookie('ASPSESSIONIDTEST','initial-fixture')
            || ($args['headers']['Origin'] ?? '') !== 'https://portale.fitet.org'
            || ($args['headers']['Referer'] ?? '') !== 'https://portale.fitet.org/login/login.asp') {
            $status=403;
        } elseif ($mode === 'bad_credentials') {
            $body='<form><input type="password" name="password"></form>';
        } else {
            $status=302;
            $headers['location']=$mode==='foreign_redirect'?'https://example.invalid/collect':($mode==='destructive_redirect'?'/login/iscrizioni/elimina_iscrizione.asp':'/login/gestione_user.asp');
            $cookies[] = new WP_Http_Cookie(['name'=>'ASPSESSIONIDTEST','value'=>'authenticated-fixture','domain'=>'portale.fitet.org','path'=>'/']);
        }
    } elseif ($path === '/login/gestione_user.asp') {
        if (!$has_cookie('ASPSESSIONIDTEST','authenticated-fixture')) { $status=403; }
        $cookies[] = new WP_Http_Cookie(['name'=>'ASPTESTSOCIETA','value'=>'718','domain'=>'portale.fitet.org','path'=>'/login/']);
        $body='<a href="iscrizioni/lista_partecipazione_tornei.asp">Riepilogo partecipazione tornei e ricevute</a>'
            .'<a href="iscrizioni/elimina_iscrizione.asp">Cancella iscrizione</a>';
    } elseif (in_array($path,['/login/iscrizioni/testa_iscrizioni_reg.asp','/login/iscrizioni/testa_iscrizioni.asp'],true)) {
        if (!$has_cookie('ASPSESSIONIDTEST','authenticated-fixture') || !$has_cookie('ASPTESTSOCIETA','718')) { $status=403; }
        if ($mode==='session_expired') { $status=302;$headers['location']='/login/login.asp'; }
        elseif ($mode==='oversized') { $body=str_repeat('x',1024*1024+1); }
        elseif ($path==='/login/iscrizioni/testa_iscrizioni_reg.asp' && $mode!=='no_receipts') {
            $tournament=isset($params['PAGINA'])?'5':'8';
            $body='<a href="iscrizioni_torneo_reg.asp?TORNEO='.$tournament.'&amp;COMITATO=4&amp;TIPO=36">Torneo fixture del 31/10/2026 a Vedano Olona (VA)</a>'
                .'<a href="iscrizioni_torneo_reg.asp?TORNEO='.$tournament.'&amp;COMITATO=4&amp;TIPO=36">Torneo fixture del 31/10/2026 a Vedano Olona (VA)</a>'
                .'<a href="elimina_iscrizione.asp">Cancella</a>';
            if($mode==='pagination'&&!isset($params['PAGINA']))$body.='<a href="?PAGINA=2">Successiva</a>';
            if($mode==='many_pages')$body.='<a href="?PAGINA='.((int)($params['PAGINA']??1)+1).'">Successiva</a>';
            if($mode==='catalog_only')$body.='<a href="iscrizioni_torneo_reg.asp?TORNEO=99999&amp;COMITATO=4&amp;TIPO=36">Torneo senza ricevuta del 01/12/2026 a Milano</a>';
        } elseif ($path==='/login/iscrizioni/testa_iscrizioni.asp' && $mode==='catalog_only') {
            $body='<a href="iscrizioni_torneo.asp?TORNEO=99999&amp;X=0">Nazionale senza ricevuta del 02/12/2026 a Roma</a>';
        }
    } elseif ($path==='/login/iscrizioni/iscrizioni_torneo_reg.asp') {
        $body='<frame src="iscrizioni_torneo_reg_left.asp?TORNEO='.$params['TORNEO'].'&amp;COMITATO=4&amp;TIPO=36">';
    } elseif ($path==='/login/iscrizioni/iscrizioni_torneo_reg_left.asp') {
        $body='<a href="../../fpdf2/iscrizioni_gara_societa_reg.php?TORNEO='.$params['TORNEO'].'&amp;COMITATO=4&amp;ANNO=41&amp;SOCIETA=718">Ricevuta</a>';
    } elseif ($path === '/login/iscrizioni/lista_partecipazione_tornei.asp') {
        if (!$has_cookie('ASPSESSIONIDTEST','authenticated-fixture') || !$has_cookie('ASPTESTSOCIETA','718')) { $status=403; }
        if ($mode==='session_expired') { $status=302;$headers['location']='/login/login.asp'; }
        elseif ($mode==='oversized') { $body=str_repeat('x',1024*1024+1); }
        else {
            $tournament=isset($params['PAGINA'])?'5':'8';
            $body='<table class="style1"><tr><td>Torneo della società</td><td><a href="../../fpdf2/iscrizioni_gara_societa_reg.php?TORNEO='.$tournament.'&amp;ANNO=41&amp;COMITATO=4&amp;SOCIETA=718">STAMPA RICEVUTA</a></td></tr></table>'
                .'<a href="../../fpdf2/iscrizioni_gara_societa_reg.php?SOCIETA=718&amp;COMITATO=4&amp;ANNO=41&amp;TORNEO='.$tournament.'">Duplicato</a>'
                .'<a href="../../fpdf2/iscrizioni_gara_societa_reg.php?TORNEO=8&amp;ANNO=41&amp;COMITATO=4&amp;SOCIETA=999">Altra società</a>'
                .'<a href="/login/iscrizioni/elimina_iscrizione.asp?TORNEO=8">Cancella iscrizione</a>';
            if ($mode==='pagination' && !isset($params['PAGINA'])) { $body.='<a href="?PAGINA=2">Successiva</a>'; }
            if ($mode==='many_pages') { $body.='<a href="?PAGINA='.((int)($params['PAGINA']??1)+1).'">Successiva</a>'; }
            if ($mode==='no_receipts') { $body='<table class="style1"></table>'; }
        }
    } elseif ($path === '/fpdf2/iscrizioni_gara_societa_reg.php') {
        if (!empty($args['cookies'])) { return new WP_Error('unexpected_pdf_cookie','Public PDF downloads must not carry login cookies.'); }
        $body = get_option('fr_test_bad_pdf') ? '<html>Sessione scaduta</html>' : file_get_contents(dirname(__DIR__, 3) . '/ricevuta-esempio.pdf');
    } else { return new WP_Error('unexpected_test_url', 'Unexpected extranet URL in test.'); }
    return ['headers' => $headers, 'body' => $body, 'response' => ['code' => $status, 'message' => 'Fixture'], 'cookies' => $cookies, 'filename' => null];
}, 5, 3);

