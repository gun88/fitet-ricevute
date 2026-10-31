<?php

defined('ABSPATH') || exit;

final class FR_Extranet_Client {
    private const ORIGIN = 'https://portale.fitet.org';
    private const LOGIN = '/login/login.asp';
    private const AUTH = '/login/autentica.asp';
    private const HOME = '/login/gestione_user.asp';
    private const LISTS = ['/login/iscrizioni/testa_iscrizioni_reg.asp', '/login/iscrizioni/testa_iscrizioni.asp'];
    private const RECEIPTS = '/login/iscrizioni/lista_partecipazione_tornei.asp';
    private const MAX_BYTES = 1024 * 1024;
    private array $cookies = [];

    private function destination(string $url): array {
        $parts = wp_parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'portale.fitet.org'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || !in_array($parts['path'] ?? '', array_merge([self::LOGIN, self::AUTH, self::HOME], self::LISTS, [self::RECEIPTS]), true)) {
            throw new RuntimeException('Destinazione extranet non consentita.');
        }
        return $parts;
    }

    private function page(string $url, ?array $body = null, string $referer = ''): array {
        for ($redirect = 0; $redirect < 6; $redirect++) {
            $this->destination($url);
            $headers = ['User-Agent' => 'FITET Ricevute/' . FITET_RICEVUTE_VERSION, 'Accept' => 'text/html,application/xhtml+xml'];
            if ($referer !== '') { $headers['Referer'] = $referer; }
            if ($body !== null) {
                $headers['Origin'] = self::ORIGIN;
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
            $response = wp_safe_remote_request($url, [
                'method' => $body === null ? 'GET' : 'POST', 'body' => $body ?? '',
                'cookies' => array_values(array_filter($this->cookies, fn(WP_Http_Cookie $cookie) => $cookie->test($url))),
                'timeout' => 12, 'redirection' => 0, 'limit_response_size' => self::MAX_BYTES + 1,
                'sslverify' => true, 'headers' => $headers,
            ]);
            if (is_wp_error($response)) { throw new RuntimeException('Extranet FITeT non raggiungibile.'); }
            foreach (wp_remote_retrieve_cookies($response) as $cookie) {
                $key = strtolower((string) $cookie->domain) . '|' . $cookie->path . '|' . $cookie->name;
                if ($cookie->expires !== null && (int) $cookie->expires < time()) { unset($this->cookies[$key]); }
                else { $this->cookies[$key] = $cookie; }
            }
            $status = wp_remote_retrieve_response_code($response);
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = wp_remote_retrieve_header($response, 'location');
                if (!is_string($location) || $location === '') { throw new RuntimeException('Reindirizzamento extranet senza destinazione.'); }
                $next = WP_Http::make_absolute_url($location, $url);
                $this->destination($next); // Validate before forwarding any session cookie or POST body.
                $referer = $url; $url = $next;
                if (in_array($status, [301, 302, 303], true)) { $body = null; }
                continue;
            }
            if ($status === 401 || $status === 403) { throw new RuntimeException('Accesso extranet non riuscito o non autorizzato. Verifica le credenziali della società.'); }
            if ($status !== 200) { throw new RuntimeException('Risposta extranet FITeT non valida (HTTP ' . $status . ').'); }
            $html = wp_remote_retrieve_body($response);
            if (strlen($html) > self::MAX_BYTES) { throw new RuntimeException('Pagina extranet troppo grande: elenco incompleto, sincronizzazione non avviata.'); }
            return ['url' => $url, 'html' => $html];
        }
        throw new RuntimeException('Troppi reindirizzamenti nell’extranet FITeT.');
    }

    private function dom(string $html): DOMDocument {
        if (!mb_check_encoding($html, 'UTF-8')) { $html = mb_convert_encoding($html, 'UTF-8', 'Windows-1252'); }
        $dom = new DOMDocument(); $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        return $dom;
    }

    private function authenticated(array $page): DOMDocument {
        $dom = $this->dom($page['html']);
        if (wp_parse_url($page['url'], PHP_URL_PATH) === self::LOGIN) {
            throw new RuntimeException('Accesso extranet non riuscito o sessione scaduta.');
        }
        foreach ($dom->getElementsByTagName('input') as $input) {
            if (strtolower($input->getAttribute('type')) === 'password') {
                throw new RuntimeException('Accesso extranet non riuscito o sessione scaduta.');
            }
        }
        return $dom;
    }

    /**
     * Reads both catalogs and the society's receipt list in one authenticated session.
     * The optional credentials stay in memory
     * and allow a backend caller to avoid saving them; REST always uses admin settings.
     */
    public function discover(string $society, ?array $credentials = null): array {
        if (!ctype_digit($society) || (ltrim($society, '0') ?: '0') === '0') { throw new RuntimeException('Codice società FITeT non valido.'); }
        $society = ltrim($society, '0');
        $this->cookies = [];
        try {
            $credentials ??= FR_Extranet_Credentials::for_sync();
            if (is_wp_error($credentials)) { throw new RuntimeException($credentials->get_error_message()); }
            if (!is_string($credentials['username'] ?? null) || !is_string($credentials['password'] ?? null)
                || $credentials['username'] === '' || $credentials['password'] === '') {
                throw new RuntimeException('Credenziali extranet mancanti o non valide.');
            }
            // Start a fresh ASP session. Never reuse a browser's pasted session/analytics cookies.
            $login = $this->page(self::ORIGIN . self::LOGIN);
            $form = null; $payload = [];
            foreach ($this->dom($login['html'])->getElementsByTagName('form') as $candidate) {
                foreach ($candidate->getElementsByTagName('input') as $input) {
                    if (strtolower($input->getAttribute('type')) === 'password') { $form = $candidate; break 2; }
                }
            }
            if (!$form) { throw new RuntimeException('Modulo di accesso FITeT non riconosciuto.'); }
            $user_field = null; $password_field = null;
            foreach ($form->getElementsByTagName('input') as $input) {
                $name = $input->getAttribute('name'); $type = strtolower($input->getAttribute('type'));
                if ($name === '') { continue; }
                if ($type === 'hidden') { $payload[$name] = $input->getAttribute('value'); }
                if ($type === 'password') { $password_field = $name; }
                if (in_array($type, ['', 'text'], true) && preg_match('/login|user|utente/i', $name)) { $user_field = $name; }
            }
            if (!$user_field || !$password_field) { throw new RuntimeException('Campi di accesso FITeT non riconosciuti.'); }
            $action = WP_Http::make_absolute_url($form->getAttribute('action'), $login['url']);
            $parts = $this->destination($action);
            if ($parts['path'] !== self::AUTH) { throw new RuntimeException('Destinazione di autenticazione FITeT cambiata.'); }
            parse_str($parts['query'] ?? '', $auth_params);
            if (isset($auth_params['COMIT']) && $auth_params['COMIT'] !== '30') { throw new RuntimeException('Modulo di accesso al portale nazionale FITeT non riconosciuto.'); }
            $action = self::ORIGIN . self::AUTH . '?COMIT=30';
            $payload['paginaOriginale'] ??= '';
            $payload[$user_field] = $credentials['username']; $payload[$password_field] = $credentials['password'];
            $home = $this->page($action, $payload, $login['url']);
            unset($credentials, $payload);
            $this->authenticated($home);
            $queue = array_map(fn($path) => self::ORIGIN . $path, array_merge(self::LISTS, [self::RECEIPTS]));
            $queued = array_fill_keys($queue, true); $tournaments = []; $receipts = []; $pages = 0;
            while ($queue) {
                if (++$pages > 12) { throw new RuntimeException('Scansione elenchi incompleta: limite pagine raggiunto.'); }
                $target = array_shift($queue); $page = $this->page($target, null, $home['url']);
                $dom = $this->authenticated($page); $page_path = wp_parse_url($target, PHP_URL_PATH);
                if ($page_path !== wp_parse_url($page['url'], PHP_URL_PATH)) { throw new RuntimeException('Elenco FITeT non disponibile per questo account.'); }
                foreach ((new DOMXPath($dom))->query('//a[@href]') as $node) {
                    $url = WP_Http::make_absolute_url(html_entity_decode($node->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $page['url']);
                    $parts = wp_parse_url($url); $path = $parts['path'] ?? '';
                    if (strtolower($parts['host'] ?? '') !== 'portale.fitet.org') { continue; }
                    if ($page_path === self::RECEIPTS && preg_match('~^/fpdf2/iscrizioni_gara_societa(?:_reg)?\\.php$~D', $path)) {
                        $source = FR_Receipt_Source::identify($url);
                        if ($source['society_code'] !== $society) { continue; }
                        $receipts[$source['tournament_key']] = $source['url'];
                    } elseif (in_array($page_path, self::LISTS, true) && in_array($path, ['/login/iscrizioni/iscrizioni_torneo_reg.asp','/login/iscrizioni/iscrizioni_torneo.asp'], true)) {
                        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['port']) || isset($parts['user']) || isset($parts['pass'])) { throw new RuntimeException('Link catalogo non valido.'); }
                        parse_str($parts['query'] ?? '', $params);
                        if (array_diff(array_keys($params), ['TORNEO','COMITATO','TIPO','X'])) { throw new RuntimeException('Parametri torneo non riconosciuti.'); }
                        foreach ($params as $value) { if (!is_string($value) || !ctype_digit($value)) { throw new RuntimeException('Parametri torneo non numerici.'); } }
                        if (!isset($params['TORNEO'])) { throw new RuntimeException('Identificativo torneo mancante.'); }
                        $text = trim(preg_replace('/\\s+/u', ' ', $node->textContent));
                        if (!preg_match('~^(.+) del (\\d{2}/\\d{2}/\\d{4}) a (.+)$~u', $text, $match)) { throw new RuntimeException('Descrizione torneo nel catalogo non riconosciuta.'); }
                        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $match[2]);
                        if (!$date || $date->format('d/m/Y') !== $match[2]) { throw new RuntimeException('Data torneo non valida.'); }
                        $committee = $path === '/login/iscrizioni/iscrizioni_torneo.asp' ? '30' : ($params['COMITATO'] ?? '');
                        if ($committee === '') { throw new RuntimeException('Comitato torneo mancante.'); }
                        $committee = ltrim($committee, '0') ?: '0'; $number = ltrim($params['TORNEO'], '0') ?: '0';
                        ksort($params); $url = self::ORIGIN . $path . '?' . http_build_query($params);
                        $key = hash('sha256', 'catalog:' . $committee . ':' . $number . ':' . $date->format('Y-m-d'));
                        $tournaments[$key] = ['key'=>$key,'url'=>$url,'committee'=>$committee,'number'=>$number,'name'=>$match[1],'date'=>$date->format('Y-m-d'),'place'=>$match[3]];
                    } elseif (in_array($path, array_merge(self::LISTS, [self::RECEIPTS]), true)) {
                        $this->destination($url);
                        parse_str($parts['query'] ?? '', $params);
                        if (array_diff(array_keys($params), ['ANNO','STAGIONE','PAGINA','PAGE','PAG'])) { continue; }
                        foreach ($params as $value) { if (!is_string($value) || !ctype_digit($value)) { continue 2; } }
                        ksort($params); $url = self::ORIGIN . $path . ($params ? '?' . http_build_query($params) : '');
                        if (!isset($queued[$url])) { $queue[] = $url; $queued[$url] = true; }
                    }
                }
            }
            return ['tournaments'=>array_values($tournaments), 'receipts'=>array_values($receipts)];
        } finally { $this->cookies = []; unset($credentials, $payload); }
    }
}

