<?php

defined('ABSPATH') || exit;

final class FR_Api {
    public const NS = 'fitet-ricevute/v1';

    public function __construct(private FR_Auth $auth, private FR_Data_Repository $data) {}

    public function init(): void {
        add_action('rest_api_init', [$this, 'routes']);
        add_filter('rest_pre_serve_request',[$this,'serve_original_pdf'],10,4);
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
        register_rest_route(self::NS, '/profilo/tabelle', [
            'methods' => 'PUT', 'permission_callback' => [$this->auth, 'permission'],
            'args' => [
                'auto_height' => ['required' => true, 'type' => 'boolean'],
                'payment_method'=>['type'=>'string','enum'=>['transfer','cash'],'default'=>'transfer'],
                'density' => ['required' => true, 'type' => 'string', 'enum' => ['standard', 'compact']],
            ],
            'callback' => function (WP_REST_Request $request) {
                $user_id = (int) $this->auth->current()['id'];
                update_option('fr_grid_preferences_' . $user_id, ['auto_height' => (bool) $request['auto_height'], 'density' => $request['density'], 'payment_method'=>$request['payment_method']], false);
                return FR_Auth::grid_preferences($user_id);
            },
        ]);
        register_rest_route(self::NS,'/tornei/manuali',['methods'=>'POST','permission_callback'=>[$this->auth,'permission'],'callback'=>fn(WP_REST_Request $r)=>$this->attempt(function()use($r){$data=json_decode((string)$r->get_param('data'),true);if(!is_array($data))throw new RuntimeException('Dati torneo non validi.');return FR_Manual_Tournament::create($data,$r->get_file_params(),(int)$this->auth->current()['id']);})]);
        register_rest_route(self::NS,'/tornei/(?P<key>[a-f0-9]{64})/locandina',['methods'=>'GET','permission_callback'=>[$this->auth,'permission'],'callback'=>fn(WP_REST_Request $r)=>$this->attempt(fn()=>new WP_REST_Response(FR_Manual_Tournament::poster($r['key']),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="locandina.pdf"']))]);
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
        register_rest_route(self::NS,'/timbro',['methods'=>'GET','permission_callback'=>[$this->auth,'permission'],'callback'=>fn()=>['stamp'=>FR_Signature::snapshot('stamp')]]);
        register_rest_route(self::NS,'/firma',['methods'=>'GET','permission_callback'=>[$this->auth,'permission'],'callback'=>fn()=>['signature'=>FR_Signature::snapshot()]]);
        $workflow=new FR_Refund_Workflow($this->data);
        $current_user=fn()=>(int)$this->auth->current()['id'];
        register_rest_route(self::NS,'/carrello',[
            ['methods'=>'GET','permission_callback'=>[$this->auth,'permission'],'callback'=>fn()=>$this->attempt(fn()=>$workflow->cart($current_user()))],
            ['methods'=>'POST','permission_callback'=>[$this->auth,'permission'],'args'=>$selection_args,'callback'=>fn(WP_REST_Request $r)=>$this->attempt(fn()=>$workflow->cart_change($current_user(),$r['ids'],true))],
            ['methods'=>'DELETE','permission_callback'=>[$this->auth,'permission'],'args'=>$selection_args,'callback'=>fn(WP_REST_Request $r)=>$this->attempt(fn()=>$workflow->cart_change($current_user(),$r['ids'],false))],
        ]);
        register_rest_route(self::NS,'/carrello/(?P<entry_id>\d+)',['methods'=>'PATCH','permission_callback'=>[$this->auth,'permission'],'callback'=>fn(WP_REST_Request $r)=>$this->attempt(fn()=>$workflow->update_line($current_user(),(int)$r['entry_id'],$r->get_json_params()??[]))]);
        register_rest_route(self::NS,'/emissioni',[
            ['methods'=>'GET','permission_callback'=>[$this->auth,'permission'],'args'=>['page'=>['type'=>'integer','minimum'=>1,'default'=>1]],'callback'=>fn(WP_REST_Request $r)=>$this->attempt(fn()=>$workflow->emissions((int)$r['page']))],
            ['methods'=>'POST','permission_callback'=>[$this->auth,'permission'],'args'=>array_merge($selection_args,['request_key'=>['required'=>true,'type'=>'string'],'prices'=>['required'=>true,'type'=>'object'],'options'=>['required'=>true,'type'=>'object']]),'callback'=>fn(WP_REST_Request $r)=>$this->attempt(fn()=>$workflow->issue($current_user(),$r->get_json_params()))],
        ]);
        register_rest_route(self::NS,'/emissioni/(?P<id>\d+)',['methods'=>'PUT','permission_callback'=>[$this->auth,'permission'],'callback'=>fn(WP_REST_Request $r)=>$this->attempt(fn()=>$workflow->revise($current_user(),(int)$r['id'],$r->get_json_params()??[]))]);
        register_rest_route(self::NS,'/emissioni/(?P<id>\d+)',['methods'=>'GET','permission_callback'=>[$this->auth,'permission'],'callback'=>fn(WP_REST_Request $r)=>$this->attempt(fn()=>$workflow->emission((int)$r['id']))]);
        register_rest_route(self::NS,'/emissioni/(?P<id>\d+)/ricevute',['methods'=>'GET','permission_callback'=>[$this->auth,'permission'],'callback'=>fn(WP_REST_Request $r)=>$this->attempt(fn()=>$workflow->documents((int)$r['id']))]);
        register_rest_route(self::NS,'/emissioni/(?P<id>\d+)/ricevute/(?P<receipt>[a-f0-9]{64})',['methods'=>'GET','permission_callback'=>[$this->auth,'permission'],'callback'=>fn(WP_REST_Request $r)=>$this->attempt(function()use($workflow,$r){$bytes=$workflow->original_pdf((int)$r['id'],$r['receipt']);return new WP_REST_Response($bytes,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="ricevuta-originale.pdf"']);})]);
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

    public function serve_original_pdf($served,$response,$request,$server) {
        if(!$served&&preg_match('~^/'.self::NS.'(?:/emissioni/\d+/ricevute/[a-f0-9]{64}|/tornei/[a-f0-9]{64}/locandina)$~D',$request->get_route())&&$response->get_status()===200&&($response->get_headers()['Content-Type']??'')==='application/pdf') {echo $response->get_data();return true;}return $served;
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
