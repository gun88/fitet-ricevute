<?php

defined('ABSPATH') || exit;

final class FR_Auth {
    public function __construct(private FR_Repository $repository) {}

    public static function cookie_name(): string {
        return 'fitet_ricevute_' . substr(hash('sha256', home_url('/')), 0, 12);
    }

    private function token(): string {
        $token = $_COOKIE[self::cookie_name()] ?? '';
        return is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : '';
    }

    public function current(): ?array {
        $token = $this->token();
        return $token !== '' ? $this->repository->session($token) : null;
    }

    private function csrf(string $token): string {
        return hash_hmac('sha256', 'fitet-ricevute-csrf:' . $token, wp_salt('auth'));
    }

    private function cookie(string $token, int $expires): bool {
        return setcookie(self::cookie_name(), $token, [
            'expires' => $expires,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function origin_permission(WP_REST_Request $request) {
        if ($request->get_header('X-FR-Request') !== '1') {
            return new WP_Error('fr_invalid_request', 'Richiesta non valida.', ['status' => 403]);
        }
        $origin = (string) $request->get_header('Origin');
        // Explicit origin validation also protects login against cross-site session swapping.
        $expected = wp_parse_url(home_url('/'));
        $actual = wp_parse_url($origin);
        if (!$actual || !isset($actual['scheme'], $actual['host']) || isset($actual['user'], $actual['pass'])
            || strtolower($actual['scheme']) !== strtolower($expected['scheme'])
            || strtolower($actual['host']) !== strtolower($expected['host'])
            || ($actual['port'] ?? ($actual['scheme'] === 'https' ? 443 : 80)) !== ($expected['port'] ?? ($expected['scheme'] === 'https' ? 443 : 80))) {
            return new WP_Error('fr_invalid_origin', 'Origine della richiesta non consentita.', ['status' => 403]);
        }
        return true;
    }

    public function permission(WP_REST_Request $request) {
        $restore=get_option('fr_backup_restore_lock');
        if($restore&&time()-(int)$restore<300)return new WP_Error('fr_restoring','Ripristino dati in corso. Riprova tra poco.',['status'=>503]);
        if (!FR_Settings::all()['enabled'] && $request->get_route() !== '/' . FR_Api::NS . '/auth/logout') {
            return new WP_Error('fr_disabled', 'Il portale è temporaneamente disabilitato.', ['status' => 503]);
        }
        if (!$this->current()) {
            return new WP_Error('fr_unauthorized', 'Accedi per continuare.', ['status' => 401]);
        }
        if ($request->get_method() === 'GET' && (string) $request->get_header('Origin') !== '') {
            $origin = self::origin_permission($request);
            if (is_wp_error($origin)) {
                return $origin;
            }
        }
        if ($request->get_method() !== 'GET') {
            $origin = self::origin_permission($request);
            if (is_wp_error($origin)) {
                return $origin;
            }
            if (!hash_equals($this->csrf($this->token()), (string) $request->get_header('X-FR-CSRF'))) {
                return new WP_Error('fr_invalid_csrf', 'Sessione non valida. Ricarica la pagina.', ['status' => 403]);
            }
        }
        return true;
    }

    private function rate_key(string $value): string {
        return 'fr_login_' . hash_hmac('sha256', $value, wp_salt('auth'));
    }

    public function login(WP_REST_Request $request) {
        $config = FR_Settings::all();
        if (!$config['enabled']) {
            return new WP_Error('fr_disabled', 'Il portale è temporaneamente disabilitato.', ['status' => 503]);
        }
        $username = strtolower(trim($request->get_param('username')));
        $password = $request->get_param('password');
        // Do not trust client-supplied forwarding headers for rate limiting.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $keys = [$this->rate_key('ip:' . $ip), $this->rate_key('user:' . $username)];
        foreach ($keys as $key) {
            if ((int) get_transient($key) >= 10) {
                $response = new WP_REST_Response([
                    'code' => 'fr_rate_limited',
                    'message' => 'Troppi tentativi. Riprova tra 15 minuti.',
                ], 429);
                $response->header('Retry-After', '900');
                return $response;
            }
        }
        $user = $this->repository->user_by_username($username);
        // Dummy verification keeps unknown and disabled users on the password-check path.
        $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = wp_check_password($password, $hash);
        if (!$valid || !$user || !(int) $user['enabled']) {
            foreach ($keys as $key) {
                set_transient($key, (int) get_transient($key) + 1, 15 * MINUTE_IN_SECONDS);
            }
            return new WP_Error('fr_invalid_credentials', 'Username o password non validi.', ['status' => 401]);
        }
        // Clear only this account's counter; a successful account must not reset IP throttling.
        delete_transient($keys[1]);
        $remember = (bool) $request->get_param('remember');
        $expires = time() + ($remember ? $config['remember_days'] * DAY_IN_SECONDS : $config['session_hours'] * HOUR_IN_SECONDS);
        $token = bin2hex(random_bytes(32));
        if (!$this->repository->create_session((int) $user['id'], $token, $expires)) {
            return new WP_Error('fr_session_failed', 'Impossibile avviare la sessione.', ['status' => 500]);
        }
        if (!$this->cookie($token, $remember ? $expires : 0)) {
            $this->repository->delete_session($token);
            return new WP_Error('fr_cookie_failed', 'Impossibile avviare la sessione.', ['status' => 500]);
        }
        if ($this->token() !== '') {
            $this->repository->delete_session($this->token());
        }
        return $this->payload($user, $expires, $token);
    }

    private function payload(array $user, int $expires, string $token): array {
        return [
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
            ],
            'grid_preferences' => self::grid_preferences((int) $user['id']),
            'expires_at' => $expires,
            'csrf_token' => $this->csrf($token),
        ];
    }

    public static function grid_preferences(int $user_id): array {
        $saved = get_option('fr_grid_preferences_' . $user_id, []);
        return ['auto_height' => ($saved['auto_height'] ?? false) === true, 'payment_method'=>($saved['payment_method']??'transfer')==='cash'?'cash':'transfer', 'density' => ($saved['density'] ?? '') === 'compact' ? 'compact' : 'standard'];
    }

    public function me(): array {
        $user = $this->current();
        return $this->payload($user, (int) $user['expires_at'], $this->token());
    }

    public function change_password(WP_REST_Request $request) {
        global $wpdb;$session=$this->current();
        if(!$session)return new WP_Error('fr_unauthorized','Accedi per continuare.',['status'=>401]);
        $id=(int)$session['id'];$key='fr_password_attempts_'.$id;
        if((int)get_transient($key)>=5)return new WP_Error('fr_password_limited','Troppi tentativi. Riprova tra 15 minuti.',['status'=>429]);
        $input=$request->get_json_params()??[];$current=$input['current_password']??null;$next=$input['new_password']??null;$confirm=$input['confirm_password']??null;
        if(!is_string($current)||strlen($current)>4096||!is_string($next)||strlen($next)<12||strlen($next)>4096||$next!==$confirm)return new WP_Error('fr_password_invalid','La nuova password deve avere da 12 a 4096 caratteri e coincidere con la conferma.',['status'=>422]);
        $user=$this->repository->user($id);
        if(!$user||!wp_check_password($current,$user['password_hash'])){set_transient($key,(int)get_transient($key)+1,15*MINUTE_IN_SECONDS);return new WP_Error('fr_password_current','Password attuale non corretta.',['status'=>422]);}
        if(wp_check_password($next,$user['password_hash']))return new WP_Error('fr_password_same','Scegli una password diversa da quella attuale.',['status'=>422]);
        if($wpdb->query('START TRANSACTION')===false)return new WP_Error('fr_password_failed','Salvataggio non riuscito.',['status'=>500]);
        try{
            if($wpdb->update(FR_Repository::users_table(),['password_hash'=>wp_hash_password($next)],['id'=>$id,'password_hash'=>$user['password_hash']])!==1)throw new RuntimeException('Account modificato: accedi nuovamente e riprova.');
            if($wpdb->delete(FR_Repository::sessions_table(),['user_id'=>$id])===false||$wpdb->query('COMMIT')===false)throw new RuntimeException('Salvataggio non riuscito.');
        }catch(Throwable $e){$wpdb->query('ROLLBACK');return new WP_Error('fr_password_failed',$e->getMessage(),['status'=>422]);}
        delete_transient($key);$this->cookie('',time()-DAY_IN_SECONDS);return ['success'=>true];
    }

    public function logout(): array {
        $this->repository->delete_session($this->token());
        $this->cookie('', time() - DAY_IN_SECONDS);
        return ['success' => true];
    }
}
