<?php

defined('ABSPATH') || exit;

final class FR_Sync_Service {
    public function __construct(private FR_Data_Repository $repository) {}

    public static function status(): array {
        $society = (string) FR_Settings::all()['society_code'];
        $value = get_option('fr_last_sync_' . hash('sha256', $society), null);
        return ['last_sync_at' => is_string($value) ? $value : null];
    }

    public function start(int $user_id): array {
        $society = (string) FR_Settings::all()['society_code'];
        if ($society === '') { throw new RuntimeException('Configura prima il codice società FITeT in wp-admin.'); }
        $scan = (new FR_Extranet_Client())->discover($society);
        $this->repository->import_catalog($scan['tournaments'], $society);
        $links = $scan['receipts'];
        $id = bin2hex(random_bytes(16));
        $job = ['id' => $id, 'user_id' => $user_id, 'society_code' => $society, 'links' => $links, 'data_generation'=>get_option('fitet_ricevute_data_generation',0),
            'total' => count($links), 'processed' => 0, 'imported' => 0, 'errors' => [], 'done' => !$links];
        set_transient('fr_sync_' . $id, $job, 20 * MINUTE_IN_SECONDS);
        if (!$links) { update_option('fr_last_sync_' . hash('sha256', $society), gmdate('Y-m-d\TH:i:s\Z'), false); }
        return $this->public_job($job);
    }

    private function public_job(array $job): array {
        return array_intersect_key($job, array_flip(['id', 'total', 'processed', 'imported', 'errors', 'done']));
    }

    public function step(string $id, int $user_id): array {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) { throw new RuntimeException('Sincronizzazione non valida.'); }
        $lock = 'fr_sync_lock_' . $id;
        if (!add_option($lock, time(), '', false)) { throw new RuntimeException('Questa sincronizzazione è già in corso. Riprova tra qualche secondo.'); }
        try {
            $job = get_transient('fr_sync_' . $id);
            if (!$job || $job['user_id'] !== $user_id) { throw new RuntimeException('Sincronizzazione scaduta o non autorizzata.'); }
            if ((string) FR_Settings::all()['society_code'] !== $job['society_code']) { throw new RuntimeException('Codice società modificato: avvia una nuova sincronizzazione.'); }
            if(($job['data_generation']??0)!==get_option('fitet_ricevute_data_generation',0))throw new RuntimeException('Dati ripristinati: avvia una nuova sincronizzazione.');
            if ($job['done']) { return $this->public_job($job); }
            $url = $job['links'][$job['processed']];
            try { $this->download_import($url, $job['society_code']); $job['imported']++; }
            catch (Throwable $error) { $job['errors'][] = ['receipt' => $job['processed'] + 1, 'message' => $error->getMessage()]; }
            $job['processed']++;
            $job['done'] = $job['processed'] >= $job['total'];
            if ($job['done'] && !$job['errors']) {
                update_option('fr_last_sync_' . hash('sha256', $job['society_code']), gmdate('Y-m-d\TH:i:s\Z'), false);
            }
            set_transient('fr_sync_' . $id, $job, 20 * MINUTE_IN_SECONDS);
            return $this->public_job($job);
        } finally { delete_option($lock); }
    }

    public function download_import(string $url, string $society): array {
        $source = FR_Receipt_Source::identify($url, $society);
        $response = wp_safe_remote_get($source['url'], ['cookies' => [], 'timeout' => 20, 'redirection' => 0, 'limit_response_size' => 3 * 1024 * 1024 + 1]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { throw new RuntimeException('Download della ricevuta FITeT non riuscito.'); }
        $pdf = wp_remote_retrieve_body($response);
        if (!str_starts_with($pdf, '%PDF-') || strlen($pdf) > 3 * 1024 * 1024) { throw new RuntimeException('La risposta FITeT non è un PDF valido o supera 3 MB.'); }
        $uploads = wp_upload_dir();
        if ($uploads['error']) { throw new RuntimeException('Cartella uploads non disponibile.'); }
        $directory = $uploads['basedir'] . '/fitet-ricevute/ricevute-pdf';
        if (!wp_mkdir_p($directory) || is_link($directory)) { throw new RuntimeException('Impossibile creare la cartella delle ricevute.'); }
        // Apache/IIS deny direct access; the plugin never returns physical paths in REST.
        if (!is_file($directory . '/.htaccess')) { file_put_contents($directory . '/.htaccess', "Require all denied\n"); }
        if (!is_file($directory . '/web.config')) { file_put_contents($directory . '/web.config', '<configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>'); }
        if (!is_file($directory . '/index.html')) { file_put_contents($directory . '/index.html', ''); }
        $sha = hash('sha256', $pdf);
        $filename = $source['tournament_key'] . '-' . $source['society_code'] . '-' . $sha . '.pdf';
        $path = $directory . '/' . $filename;
        $new = !is_file($path);
        if ($new && file_put_contents($path, $pdf, LOCK_EX) !== strlen($pdf)) { throw new RuntimeException('Salvataggio PDF non riuscito.'); }
        try {
            $receipt = (new FR_Receipt_Parser())->parse($path);
            return $this->repository->import($source, $receipt, $filename, $sha);
        } catch (Throwable $error) { if ($new) { unlink($path); } throw $error; }
    }
}
