<?php

defined('ABSPATH') || exit;

final class FR_Receipt_Source {
    public static function identify(string $url, string $society = ''): array {
        $parts = wp_parse_url(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || strtolower($parts['host'] ?? '') !== 'portale.fitet.org'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || !preg_match('~^/fpdf2/iscrizioni_gara_societa(?:_reg)?\.php$~D', $parts['path'] ?? '')) {
            throw new RuntimeException('URL ricevuta FITeT non riconosciuto.');
        }
        $params = [];
        foreach (explode('&', $parts['query'] ?? '') as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = strtoupper(urldecode($key));
            $value = urldecode($value);
            if (!preg_match('/^[A-Z][A-Z0-9_]{0,31}$/D', $key) || isset($params[$key]) || strlen($value) > 128
                || preg_match('/PASSWORD|TOKEN|SESSION|LOGIN/', $key)) {
                throw new RuntimeException('Parametri ricevuta FITeT non validi o duplicati.');
            }
            if (in_array($key, ['ANNO', 'TORNEO', 'COMITATO', 'SOCIETA'], true)) {
                if (!ctype_digit($value)) { throw new RuntimeException('Identificativo FITeT non numerico.'); }
                $value = ltrim($value, '0') ?: '0';
            }
            $params[$key] = $value;
        }
        if (!isset($params['ANNO'], $params['TORNEO'])) { throw new RuntimeException('ANNO o TORNEO mancanti nella ricevuta.'); }
        $society = ltrim($society, '0') ?: '';
        if ($society !== '') {
            if (isset($params['SOCIETA']) && $params['SOCIETA'] !== $society) {
                throw new RuntimeException('La ricevuta appartiene a una società diversa da quella configurata.');
            }
            $params['SOCIETA'] = $society;
        }
        if (empty($params['SOCIETA'])) { throw new RuntimeException('Società FITeT mancante.'); }
        ksort($params, SORT_STRING);
        if (!isset($params['COMITATO']) && $parts['path'] === '/fpdf2/iscrizioni_gara_societa.php') {
            $params['COMITATO'] = '30'; ksort($params, SORT_STRING);
        }
        $identity = $params;
        unset($identity['SOCIETA']);
        $reference = http_build_query($identity, '', '&', PHP_QUERY_RFC3986);
        return [
            'tournament_key' => hash('sha256', $reference), 'tournament_ref' => $reference,
            'params' => $params, 'society_code' => $params['SOCIETA'],
            'url' => $parts['scheme'] . '://portale.fitet.org' . $parts['path'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
        ];
    }
}
