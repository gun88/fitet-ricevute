<?php

defined('ABSPATH') || exit;

/** Backend-only credentials. This class is deliberately not exposed through REST. */
final class FR_Extranet_Credentials {
    public const OPTION = 'fitet_ricevute_extranet_credentials';
    private const CIPHER = 'aes-256-gcm';
    private const AAD = 'fitet-ricevute/extranet/v1';

    private static function stored(): array {
        $value = get_option(self::OPTION, []);
        return is_array($value) ? $value : [];
    }

    public static function status(): array {
        $value = self::stored();
        return [
            'username' => (string) ($value['username'] ?? ''),
            'password_saved' => !empty($value['password_encrypted']),
        ];
    }

    private static function key(): string {
        return hash_hkdf('sha256', wp_salt('auth'), 32, self::AAD);
    }

    private static function crypto_available(): bool {
        return function_exists('openssl_encrypt') && function_exists('openssl_decrypt')
            && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    private static function encrypt(string $password) {
        if (!self::crypto_available()) {
            return new WP_Error('fr_crypto_unavailable', 'Per salvare la password FITeT è necessaria l’estensione PHP OpenSSL con AES-256-GCM.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($password, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::AAD, 16);
        if ($encrypted === false) {
            return new WP_Error('fr_encrypt_failed', 'Impossibile proteggere la password FITeT. Credenziali non modificate.');
        }
        return 'v1:' . base64_encode($iv . $tag . $encrypted);
    }

    private static function decrypt(string $encrypted) {
        $error = new WP_Error('fr_decrypt_failed', 'La password FITeT salvata non è leggibile. Reinseriscila nelle configurazioni.');
        if (!self::crypto_available() || !str_starts_with($encrypted, 'v1:')) {
            return $error;
        }
        $data = base64_decode(substr($encrypted, 3), true);
        if ($data === false || strlen($data) < 29) {
            return $error;
        }
        $password = openssl_decrypt(substr($data, 28), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16), self::AAD);
        return $password === false ? $error : $password;
    }

    public static function save(array $input) {
        if (!is_string($input['username'] ?? '') || !is_string($input['password'] ?? '')) {
            return new WP_Error('fr_invalid_credentials', 'Formato delle credenziali FITeT non valido.');
        }
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        if (strlen($username) > 255 || preg_match('/[\x00-\x1f\x7f]/', $username) || strlen($password) > 4096) {
            return new WP_Error('fr_invalid_credentials', 'Username FITeT non valido o password troppo lunga.');
        }
        $clear = !empty($input['clear_password']);
        if ($clear && $password !== '') {
            return new WP_Error('fr_conflicting_credentials', 'Per rimuovere la password salvata, lascia vuoto il campo nuova password.');
        }
        $previous = self::stored();
        $encrypted = (string) ($previous['password_encrypted'] ?? '');
        if ($clear) {
            $encrypted = '';
        } elseif ($password !== '') {
            $encrypted = self::encrypt($password);
            if (is_wp_error($encrypted)) {
                return $encrypted;
            }
        }
        $value = ['username' => $username, 'password_encrypted' => $encrypted];
        if ($value !== $previous && !update_option(self::OPTION, $value, false)) {
            return new WP_Error('fr_save_failed', 'Impossibile salvare le credenziali FITeT.');
        }
        return true;
    }

    /** Future synchronizer entry point: never include this result in an API response or log. */
    public static function for_sync() {
        $stored = self::stored();
        if (empty($stored['username']) || empty($stored['password_encrypted'])) {
            return new WP_Error('fr_missing_fitet_credentials', 'Inserisci username e password dell’extranet FITeT nelle configurazioni.');
        }
        $password = self::decrypt($stored['password_encrypted']);
        return is_wp_error($password) ? $password : ['username' => $stored['username'], 'password' => $password];
    }
}
