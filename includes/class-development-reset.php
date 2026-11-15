<?php
defined('ABSPATH') || exit;

final class FR_Development_Reset {
    public static function available(): bool {
        $allowed = ['localhost', '127.0.0.1', '[::1]', '::1'];
        $site = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $request = strtolower((string) wp_parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        return in_array($site, $allowed, true) && in_array($request, $allowed, true);
    }

    public static function run(): int {
        if (!current_user_can('manage_options') || !self::available()) throw new RuntimeException('Reset disponibile solo agli amministratori in localhost.');
        global $wpdb;
        $lock = 'fr_backup_restore_lock';
        if (!add_option($lock, time(), '', false)) throw new RuntimeException('Un ripristino o reset è già in corso.');
        try {
            foreach(['fr_issue_lock','fr_manual_tournament_lock'] as $active){$started=get_option($active);if($started&&time()-(int)$started<300)throw new RuntimeException('Salvataggio dati in corso. Attendi prima del reset.');}
            // Do not reset while a receipt import is being written.
            $pattern = $wpdb->esc_like('fr_sync_lock_') . '%';
            $busy = $wpdb->get_col($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name LIKE %s", $pattern));
            foreach ($busy as $started) if (time() - (int) $started < 300) throw new RuntimeException('Sincronizzazione in corso. Attendi che termini prima del reset.');
            $uploads = wp_upload_dir();
            if ($uploads['error']) throw new RuntimeException('Cartella uploads non disponibile.');
            $base = realpath($uploads['basedir']);
            $directory = $uploads['basedir'] . '/fitet-ricevute/ricevute-pdf';
            $resolved = realpath($directory);
            $files = [];
            if (file_exists($directory)) {
                if (!$base || !$resolved || is_link(dirname($directory)) || is_link($directory) || !str_starts_with(strtolower(wp_normalize_path($resolved)), rtrim(strtolower(wp_normalize_path($base)), '/') . '/')) throw new RuntimeException('Cartella ricevute non consentita.');
                foreach (new DirectoryIterator($resolved) as $file) {
                    if ($file->isDot() || strtolower($file->getExtension()) !== 'pdf') continue;
                    if ($file->isLink() || !$file->isFile()) throw new RuntimeException('Archivio PDF non consentito.');
                    $files[] = $file->getPathname();
                }
            }
            $check = static function ($value): void { if ($value === false) throw new RuntimeException('Reset database non riuscito.'); };
            $check($wpdb->query('START TRANSACTION'));
            try {
                foreach (array_reverse(FR_Backup::TABLES) as $name) {
                    $table = $wpdb->prefix . 'fitet_ricevute_' . $name;
                    $check($wpdb->query("DELETE FROM $table"));
                }
                $sessions = FR_Repository::sessions_table();
                $check($wpdb->query("DELETE FROM $sessions"));
                $check($wpdb->query('COMMIT'));
            } catch (Throwable $error) { $wpdb->query('ROLLBACK'); throw $error; }
            update_option('fitet_ricevute_data_generation', bin2hex(random_bytes(16)), false);
            $keys = $wpdb->get_col("SELECT option_name FROM $wpdb->options");
            foreach ($keys as $key) {
                if (preg_match('/^fr_(?:last_sync|draft_options)_[a-f0-9]{64}$/D', $key)) delete_option($key);
                if (preg_match('/^_transient_(?:timeout_)?(fr_sync_[a-f0-9]{32})$/D', $key, $match)) delete_transient($match[1]);
            }
            $remaining = 0;
            foreach ($files as $file) if (!unlink($file)) $remaining++;
            return $remaining;
        } finally { delete_option($lock); }
    }
}
