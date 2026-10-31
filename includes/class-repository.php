<?php

defined('ABSPATH') || exit;

final class FR_Repository {
    public static function users_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'fitet_ricevute_users';
    }

    public static function sessions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'fitet_ricevute_sessions';
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $users = self::users_table();
        $sessions = self::sessions_table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $users (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(60) NOT NULL,
            display_name varchar(120) NOT NULL,
            password_hash varchar(255) NOT NULL,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY username (username)
        ) $charset;");
        dbDelta("CREATE TABLE $sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            expires_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) $charset;");
        FR_Data_Repository::install();
        update_option('fitet_ricevute_schema', FITET_RICEVUTE_VERSION, false);
    }

    public function users(): array {
        global $wpdb;
        $table = self::users_table();
        // Password hashes are never included in lists or API responses.
        return $wpdb->get_results("SELECT id, username, display_name, enabled, created_at FROM $table ORDER BY username", ARRAY_A);
    }

    public function user(int $id): ?array {
        global $wpdb;
        $table = self::users_table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
    }

    public function user_by_username(string $username): ?array {
        global $wpdb;
        $table = self::users_table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE username = %s", $username), ARRAY_A);
    }

    public function save_user(int $id, array $data) {
        global $wpdb;
        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $display_name = sanitize_text_field($data['display_name'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,59}$/D', $username) || $display_name === '' || preg_match_all('/./us', $display_name) > 120) {
            return new WP_Error('invalid_user', 'Nome obbligatorio (massimo 120 caratteri). Username: 3–60 caratteri, lettere, numeri, punto, trattino o underscore.');
        }
        if (($id === 0 || $password !== '') && (strlen($password) < 12 || strlen($password) > 4096)) {
            return new WP_Error('invalid_password', 'La password deve contenere da 12 a 4096 caratteri.');
        }
        if ($id && !$this->user($id)) {
            return new WP_Error('user_not_found', 'Utente non trovato.');
        }
        $existing = $this->user_by_username($username);
        if ($existing && (int) $existing['id'] !== $id) {
            return new WP_Error('duplicate_user', 'Username già utilizzato.');
        }
        $record = [
            'username' => $username,
            'display_name' => $display_name,
            'enabled' => !empty($data['enabled']) ? 1 : 0,
        ];
        if ($password !== '') {
            $record['password_hash'] = wp_hash_password($password);
        }
        if ($id) {
            // Invalidate all sessions on any account edit, including password reset and disable.
            $result = $wpdb->update(self::users_table(), $record, ['id' => $id]);
            if ($result !== false) {
                $wpdb->delete(self::sessions_table(), ['user_id' => $id]);
            }
        } else {
            $record['created_at'] = current_time('mysql', true);
            $result = $wpdb->insert(self::users_table(), $record);
            $id = (int) $wpdb->insert_id;
        }
        return $result === false ? new WP_Error('save_failed', 'Impossibile salvare l’utente.') : $id;
    }

    public function create_session(int $user_id, string $token, int $expires): bool {
        global $wpdb;
        return $wpdb->insert(self::sessions_table(), [
            'user_id' => $user_id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expires,
        ]) !== false;
    }

    public function session(string $token): ?array {
        global $wpdb;
        $sessions = self::sessions_table();
        $users = self::users_table();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT u.id, u.username, u.display_name, s.expires_at
            FROM $sessions s JOIN $users u ON u.id = s.user_id
            WHERE s.token_hash = %s AND s.expires_at > %d AND u.enabled = 1",
            hash('sha256', $token), time()
        ), ARRAY_A);
    }

    public function delete_session(string $token): void {
        global $wpdb;
        $wpdb->delete(self::sessions_table(), ['token_hash' => hash('sha256', $token)]);
    }

    public function cleanup_sessions(): void {
        global $wpdb;
        $table = self::sessions_table();
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires_at <= %d", time()));
    }
}
