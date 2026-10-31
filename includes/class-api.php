<?php

defined('ABSPATH') || exit;

final class FR_Api {
    public const NS = 'fitet-ricevute/v1';

    public function __construct(private FR_Auth $auth, private FR_Data_Repository $data) {}

    public function init(): void {
        add_action('rest_api_init', [$this, 'routes']);
        add_filter('rest_post_dispatch', [$this, 'no_cache'], 10, 3);
    }

    public function routes(): void {
        register_rest_route(self::NS, '/config', [
            'methods' => 'GET',
            'callback' => [FR_Settings::class, 'public_config'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/auth/login', [
            'methods' => 'POST',
            'callback' => [$this->auth, 'login'],
            'permission_callback' => [FR_Auth::class, 'origin_permission'],
            'args' => [
                'username' => ['required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 60],
                // Passwords must never be sanitized or trimmed.
                'password' => ['required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 4096],
                'remember' => ['type' => 'boolean', 'default' => false],
            ],
        ]);
        register_rest_route(self::NS, '/auth/me', [
            'methods' => 'GET',
            'callback' => [$this->auth, 'me'],
            'permission_callback' => [$this->auth, 'permission'],
        ]);
        register_rest_route(self::NS, '/auth/logout', [
            'methods' => 'POST',
            'callback' => [$this->auth, 'logout'],
            'permission_callback' => [$this->auth, 'permission'],
        ]);
        foreach (['gare', 'iscrizioni'] as $kind) {
            register_rest_route(self::NS, '/' . $kind, [
                'methods' => 'GET', 'permission_callback' => [$this->auth, 'permission'],
                'args' => [
                    'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000, 'default' => 1],
                    'search' => ['type' => 'string', 'maxLength' => 100, 'default' => ''],
                ],
                'callback' => function (WP_REST_Request $request) use ($kind) {
                    return $this->attempt(fn() => $this->data->listing($kind, (int) $request['page'], $request['search']));
                },
            ]);
        }
        register_rest_route(self::NS, '/prezzi/(?P<race_id>\d+)', [
            'methods' => 'PATCH', 'permission_callback' => [$this->auth, 'permission'],
            // WordPress treats required + null as missing; check JSON key presence below.
            'args' => ['manual_cents' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100000000]],
            'callback' => function (WP_REST_Request $request) {
                return $this->attempt(function () use ($request) {
                    if (!array_key_exists('manual_cents', $request->get_json_params() ?? [])) { throw new RuntimeException('Indica il prezzo manuale oppure null per ripristinare il calcolo automatico.'); }
                    $this->data->set_manual_price((int) $request['race_id'], $request['manual_cents'], (int) $this->auth->current()['id']);
                    return ['success' => true];
                });
            },
        ]);
        $selection_args = ['ids' => ['required' => true, 'type' => 'array', 'minItems' => 1, 'maxItems' => 1000, 'uniqueItems' => true, 'items' => ['type' => 'integer', 'minimum' => 1]]];
        register_rest_route(self::NS, '/rimborsi/anteprima', [
            'methods' => 'POST', 'permission_callback' => [$this->auth, 'permission'], 'args' => $selection_args,
            'callback' => fn(WP_REST_Request $request) => $this->attempt(fn() => $this->data->refund_preview($request['ids'])),
        ]);
        register_rest_route(self::NS, '/rimborsi/stato', [
            'methods' => 'PATCH', 'permission_callback' => [$this->auth, 'permission'],
            'args' => array_merge($selection_args, ['reimbursed' => ['required' => true, 'type' => 'boolean']]),
            'callback' => fn(WP_REST_Request $request) => $this->attempt(fn() => $this->data->set_reimbursed($request['ids'], $request['reimbursed'], (int) $this->auth->current()['id'])),
        ]);
        register_rest_route(self::NS, '/sincronizzazione/stato', [
            'methods' => 'GET', 'permission_callback' => [$this->auth, 'permission'],
            'callback' => fn() => FR_Sync_Service::status(),
        ]);
        register_rest_route(self::NS, '/sincronizzazione', [
            'methods' => 'POST', 'permission_callback' => [$this->auth, 'permission'],
            'callback' => fn() => $this->attempt(fn() => (new FR_Sync_Service($this->data))->start((int) $this->auth->current()['id'])),
        ]);
        register_rest_route(self::NS, '/sincronizzazione/(?P<job_id>[a-f0-9]{32})/passo', [
            'methods' => 'POST', 'permission_callback' => [$this->auth, 'permission'],
            'callback' => fn(WP_REST_Request $request) => $this->attempt(fn() => (new FR_Sync_Service($this->data))->step($request['job_id'], (int) $this->auth->current()['id'])),
        ]);
    }

    private function attempt(callable $callback) {
        try { return $callback(); }
        catch (Throwable $error) { return new WP_Error('fr_operation_failed', $error instanceof RuntimeException ? $error->getMessage() : 'Operazione non riuscita. Nessuna modifica parziale alla ricevuta.', ['status' => 422]); }
    }

    public function no_cache($response, $server, WP_REST_Request $request) {
        if (str_starts_with($request->get_route(), '/' . self::NS . '/')) {
            $response = rest_ensure_response($response);
            $response->header('Cache-Control', 'no-store, private, max-age=0');
            $response->header('Vary', 'Cookie, Origin');
            $response->header('X-Content-Type-Options', 'nosniff');
        }
        return $response;
    }
}
