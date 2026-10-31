<?php

defined('ABSPATH') || exit;

final class FR_Frontend {
    public function init(): void {
        add_action('init', [self::class, 'rewrite']);
        add_filter('query_vars', function ($vars) {
            $vars[] = 'fitet_ricevute_app';
            return $vars;
        });
        add_action('template_redirect', [$this, 'render'], 0);
    }

    public static function rewrite(): void {
        add_rewrite_rule('^fitet-ricevute/?$', 'index.php?fitet_ricevute_app=1', 'top');
    }

    public function render(): void {
        if (!get_query_var('fitet_ricevute_app')) {
            return;
        }
        status_header(200);
        nocache_headers();
        header('Cache-Control: no-store, private, max-age=0');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Content-Type: text/html; charset=UTF-8');
        $index = FITET_RICEVUTE_DIR . 'assets/app/index.html';
        if (!is_file($index)) {
            status_header(503);
            echo '<!doctype html><html lang="it"><meta charset="utf-8"><title>FITET Ricevute</title><p>Frontend non compilato. Consulta il README del plugin.</p></html>';
            exit;
        }
        $config = wp_json_encode([
            'apiUrl' => esc_url_raw(rest_url(FR_Api::NS . '/')),
            'appName' => FR_Settings::all()['app_name'],
            'assetsUrl' => esc_url_raw(FITET_RICEVUTE_URL . 'assets/app/'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $html = file_get_contents($index);
        $app_url = get_option('permalink_structure')
            ? home_url('/fitet-ricevute/')
            : add_query_arg('fitet_ricevute_app', '1', home_url('/'));
        $base = '<base href="' . esc_url($app_url) . '">';
        $html = preg_replace('/<base\s+href="[^"]*"\s*\/?\s*>/i', $base, $html, 1);
        // Keep the document base on the portal route. A base pointing at assets would
        // make fragment navigation change the URL to the plugin directory on refresh.
        $html = preg_replace_callback('/\b(src|href)="([^"]+)"/i', function ($match) {
            $url = $match[2];
            if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|#)~i', $url)) {
                return $match[0];
            }
            return $match[1] . '="' . esc_url(FITET_RICEVUTE_URL . 'assets/app/' . $url) . '"';
        }, $html);
        $script = '<script id="fr-config" type="application/json">' . $config . '</script>';
        $html = str_replace('<!--FR_CONFIG-->', '', $html);
        $html = str_replace('</head>', $script . '</head>', $html);
        echo $html; // Trusted local Angular build. Runtime config is JSON hex-escaped above.
        exit;
    }
}
