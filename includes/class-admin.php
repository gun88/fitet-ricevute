<?php

defined('ABSPATH') || exit;

final class FR_Admin {
    public function __construct(private FR_Repository $repository) {}

    public function init(): void {
        add_action('admin_post_fitet_ricevute_development_reset', [$this, 'development_reset']);
        add_action('admin_post_fitet_ricevute_save_config', [$this, 'save_config']);
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_action('admin_enqueue_scripts', [$this, 'styles']);
        add_action('admin_post_fitet_ricevute_save_user', [$this, 'save_user']);
        add_action('admin_post_fitet_ricevute_save_extranet', [$this, 'save_extranet']);
        add_action('admin_post_fitet_ricevute_save_signature', [$this, 'save_signature']);
    }

    public function styles(string $hook): void {
        if (str_contains($hook, 'fitet-ricevute')) {
            wp_enqueue_style('fitet-ricevute-admin', FITET_RICEVUTE_URL . 'assets/admin.css', [], FITET_RICEVUTE_VERSION);
        }
    }

    public function menu(): void {
        add_menu_page('FITET Ricevute', 'FITET Ricevute', 'manage_options', 'fitet-ricevute', [$this, 'config_page'], 'dashicons-media-spreadsheet');
        add_submenu_page('fitet-ricevute', 'Configurazione', 'Configurazione', 'manage_options', 'fitet-ricevute', [$this, 'config_page']);
        add_submenu_page('fitet-ricevute', 'Utenti del portale', 'Utenti del portale', 'manage_options', 'fitet-ricevute-users', [$this, 'users_page']);
    }

    public function settings(): void {
        register_setting('fitet_ricevute', FR_Settings::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [FR_Settings::class, 'sanitize'],
            'default' => FR_Settings::defaults(),
        ]);
    }

    private function guard(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Non hai i permessi per gestire FITET Ricevute.', '', ['response' => 403]);
        }
    }

    public function config_page(): void {
        $this->guard();
        $config = FR_Settings::all();
        $extranet = FR_Extranet_Credentials::status();
        $signature = FR_Signature::snapshot();
        $stamp = FR_Signature::snapshot('stamp');
        $fields = [
            'app_name' => ['Nome del portale', 'text'],
            'society_name' => ['Nome società', 'text'],
            'society_code' => ['Codice società FITeT', 'text'],
            'session_hours' => ['Durata sessione (ore, da 1 a 72)', 'number'],
            'remember_days' => ['Durata “Ricordami” (giorni, da 1 a 90)', 'number'],
            'manual_receipt_kb'=>['Ricevuta manuale: limite KB (1–3072)', 'number'],
            'manual_poster_kb'=>['Locandina manuale: limite KB (1–3072)', 'number'],
        ];
        ?>
        <div class="wrap fr-admin">
            <h1>FITET Ricevute — Configurazione</h1>
            <p><a class="button" href="<?php echo esc_url(home_url('/fitet-ricevute/')); ?>" target="_blank" rel="noopener">Apri il portale</a></p>
            <?php settings_errors(); ?>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fitet_ricevute_save_config">
                <?php wp_nonce_field('fitet_ricevute_save_config'); ?>
                <?php if(isset($_GET['config_saved'])): ?><div class="notice notice-success"><p>Configurazione salvata.</p></div><?php endif; ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Portale attivo</th><td><label>
                        <input name="<?php echo esc_attr(FR_Settings::OPTION); ?>[enabled]" type="checkbox" value="1" <?php checked($config['enabled']); ?>> Consenti l’accesso al portale
                    </label></td></tr>
                    <?php foreach ($fields as $key => [$label, $type]): ?>
                        <tr><th scope="row"><label for="fr-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td><input class="regular-text" id="fr-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(FR_Settings::OPTION . '[' . $key . ']'); ?>" type="<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($config[$key]); ?>" <?php echo $type === 'number' ? 'min="1" max="' . ($key === 'session_hours' ? '72' : ($key === 'remember_days' ? '90' : '3072')) . '"' : ''; ?>></td></tr>
                    <?php endforeach; ?>
                    <tr><th scope="row"><label for="fr-login-message">Messaggio nella pagina di accesso</label></th><td>
                        <textarea class="large-text" rows="3" id="fr-login-message" name="<?php echo esc_attr(FR_Settings::OPTION); ?>[login_message]"><?php echo esc_textarea($config['login_message']); ?></textarea>
                    </td></tr>
                </table>

            <h2>Firma nelle ricevute PDF</h2>
            <p>Carica una firma PNG o JPG (massimo 1 MB, 4096 × 4096 pixel). Per uno sfondo trasparente usa PNG.
            La firma viene inserita nelle nuove ricevute cumulative e individuali; quelle già emesse conservano la propria firma.</p>
            <?php if (isset($_GET['signature_saved'])): ?><div class="notice notice-success"><p>Firma delle ricevute aggiornata.</p></div><?php endif; ?>
            <?php if ($signature): ?><div class="fr-signature-preview"><img src="<?php echo esc_attr($signature['data_url']); ?>" alt="Anteprima firma delle ricevute"></div><?php else: ?><p>Nessuna firma caricata. Le ricevute avranno lo spazio per la firma manuale.</p><?php endif; ?>

                <table class="form-table" role="presentation">
                    <tr><th><label for="fr-signature-file">Immagine firma</label></th><td><input type="file" id="fr-signature-file" name="signature_file" accept="image/png,image/jpeg,.png,.jpg,.jpeg"><p class="description">Lascia vuoto per conservare la firma attuale.</p></td></tr>
                    <?php if ($signature): ?><tr><th>Rimuovi firma</th><td><label><input type="checkbox" name="clear_signature" value="1"> Rimuovi la firma dalle nuove ricevute</label></td></tr><?php endif; ?>
                </table>
            <h2>Timbro nelle ricevute PDF</h2>
            <p>PNG o JPG fino a 1 MB, massimo 4096 × 4096 pixel. Il timbro viene affiancato alla firma nelle nuove ricevute.</p>
            <?php if($stamp): ?><div class="fr-signature-preview"><img src="<?php echo esc_attr($stamp['data_url']); ?>" alt="Anteprima timbro delle ricevute"></div><?php endif; ?>
            <table class="form-table" role="presentation">
                <tr><th><label for="fr-stamp-file">Immagine timbro</label></th><td><input type="file" id="fr-stamp-file" name="stamp_file" accept="image/png,image/jpeg,.png,.jpg,.jpeg"><p class="description">Lascia vuoto per conservare il timbro attuale.</p></td></tr>
                <?php if($stamp): ?><tr><th>Rimuovi timbro</th><td><label><input type="checkbox" name="clear_stamp" value="1"> Rimuovi il timbro dalle nuove ricevute</label></td></tr><?php endif; ?>
            </table>
            <h2>Extranet FITeT</h2>
            <p>Credenziali della società per la ricerca automatica delle ricevute nell’extranet. I PDF vengono scaricati dai link pubblici.</p>
            <?php if (isset($_GET['extranet_saved'])): ?><div class="notice notice-success"><p>Credenziali extranet FITeT salvate.</p></div><?php endif; ?>

                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="fr-extranet-username">Username extranet FITeT</label></th><td>
                        <input id="fr-extranet-username" class="regular-text" name="extranet[username]" type="text" maxlength="255" autocomplete="off" autocapitalize="none" spellcheck="false" value="<?php echo esc_attr($extranet['username']); ?>">
                    </td></tr>
                    <tr><th scope="row"><label for="fr-extranet-password">Password extranet FITeT</label></th><td>
                        <input id="fr-extranet-password" class="regular-text" name="extranet[password]" type="password" maxlength="4096" autocomplete="new-password" value="">
                        <p class="description"><?php echo $extranet['password_saved'] ? 'Password già salvata. Lascia vuoto per conservarla, oppure inserisci una nuova password per sostituirla.' : 'Nessuna password salvata.'; ?></p>
                        <p class="description">La password salvata non viene mostrata.</p>
                    </td></tr>
                    <?php if ($extranet['password_saved']): ?>
                        <tr><th scope="row">Rimuovi password</th><td><label><input type="checkbox" name="extranet[clear_password]" value="1"> Rimuovi la password extranet FITeT salvata</label></td></tr>
                    <?php endif; ?>
                </table>
                <?php submit_button('Salva configurazione'); ?>
            </form>
<h2>Import/export dati</h2><p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=fitet-ricevute-backup')); ?>">Importa / esporta dati</a></p>
            <?php if (FR_Development_Reset::available()): ?>
            <h2>Strumenti di sviluppo</h2>
            <?php if (isset($_GET['development_reset'])): ?><div class="notice notice-success"><p>Dati azzerati. Configurazioni e account conservati. Accedi nuovamente al portale.</p></div><?php endif; ?>
            <?php if (!empty($_GET['remaining_pdfs'])): ?><div class="notice notice-warning"><p>Alcuni PDF non sono stati eliminati: verifica i permessi della cartella ricevute-pdf.</p></div><?php endif; ?>
            <p>Elimina tornei, gare, iscrizioni, prezzi, bozze, rimborsi e PDF originali. Conserva impostazioni, credenziali, firma, timbro, account e preferenze personali. Gli utenti del portale dovranno accedere nuovamente.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fitet_ricevute_development_reset">
                <?php wp_nonce_field('fitet_ricevute_development_reset'); ?>
                <p><label><input type="checkbox" name="confirm_reset" value="1" required> Confermo la cancellazione definitiva dei dati del plugin.</label></p>
                <button class="button" type="submit">Azzera dati di sviluppo</button>
            </form>
            <?php endif; ?>
            <h2>Funzionalità del portale</h2>
            <p>Sincronizza ricevute · Modifica prezzi tornei · Crea rimborsi</p>
            <p>Il portale gestisce ricevute, iscrizioni, prezzi, schede giocatore e ricevute di rimborso.</p>
        </div>
        <?php
    }

    public function development_reset(): void {
        $this->guard();
        if (!FR_Development_Reset::available()) wp_die('Reset disponibile solo in localhost.', '', ['response' => 403]);
        check_admin_referer('fitet_ricevute_development_reset');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['confirm_reset'] ?? '') !== '1') wp_die('Conferma la cancellazione dei dati.', '', ['response' => 400]);
        try { $remaining = FR_Development_Reset::run(); }
        catch (Throwable $error) { wp_die(esc_html($error->getMessage()), 'Reset dati', ['response' => 400, 'back_link' => true]); }
        wp_safe_redirect(add_query_arg(['page' => 'fitet-ricevute', 'development_reset' => '1', 'remaining_pdfs' => $remaining], admin_url('admin.php')));
        exit;
    }

    public function save_config(): void {
        $this->guard();check_admin_referer('fitet_ricevute_save_config');
        try {FR_Settings::save_configuration(['settings'=>wp_unslash($_POST[FR_Settings::OPTION]??[]),'extranet'=>wp_unslash($_POST['extranet']??[]),'clear_signature'=>!empty($_POST['clear_signature']),'clear_stamp'=>!empty($_POST['clear_stamp'])],$_FILES);}
        catch(Throwable $error){wp_die(esc_html($error instanceof RuntimeException?$error->getMessage():'Configurazione non salvata.'),'Configurazione non salvata',['response'=>400,'back_link'=>true]);}
        wp_safe_redirect(admin_url('admin.php?page=fitet-ricevute&config_saved=1'));exit;
    }

    public function save_signature(): void {
        $this->guard();check_admin_referer('fitet_ricevute_save_signature');
        try {
            $file=$_FILES['signature_file']??[];
            if(!empty($_POST['clear_signature'])){
                if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)throw new RuntimeException('Scegli se caricare una nuova firma oppure rimuovere quella attuale.');
                FR_Signature::remove();
            } else { FR_Signature::upload($file); }
        }catch(RuntimeException $error){wp_die(esc_html($error->getMessage()),'Firma non salvata',['response'=>400,'back_link'=>true]);}
        wp_safe_redirect(admin_url('admin.php?page=fitet-ricevute&signature_saved=1'));exit;
    }

    public function save_extranet(): void {
        $this->guard();
        check_admin_referer('fitet_ricevute_save_extranet');
        $input = wp_unslash($_POST['extranet'] ?? []);
        $result = FR_Extranet_Credentials::save(is_array($input) ? $input : []);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()), 'Credenziali FITeT non salvate', ['response' => 400, 'back_link' => true]);
        }
        wp_safe_redirect(admin_url('admin.php?page=fitet-ricevute&extranet_saved=1'));
        exit;
    }

    public function save_user(): void {
        $this->guard();
        check_admin_referer('fitet_ricevute_save_user');
        $id = absint($_POST['user_id'] ?? 0);
        $result = $this->repository->save_user($id, wp_unslash($_POST));
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()), 'Utente non salvato', ['response' => 400, 'back_link' => true]);
        }
        wp_safe_redirect(admin_url('admin.php?page=fitet-ricevute-users&saved=1'));
        exit;
    }

    public function users_page(): void {
        $this->guard();
        $edit_id = absint($_GET['edit'] ?? 0);
        $edit = $edit_id ? $this->repository->user($edit_id) : null;
        if ($edit_id && !$edit) {
            wp_die('Utente non trovato.', '', ['response' => 404]);
        }
        ?>
        <div class="wrap fr-admin">
            <h1>FITET Ricevute — Utenti del portale</h1>
            <p>Questi account accedono solo al portale FITET Ricevute e sono separati dagli utenti WordPress e ResPong.</p>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Utente salvato. Le sue sessioni precedenti sono state revocate.</p></div><?php endif; ?>
            <table class="widefat striped fr-users-table">
                <thead><tr><th>Username</th><th>Nome</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($this->repository->users() as $user): ?>
                    <tr>
                        <td data-label="Username"><?php echo esc_html($user['username']); ?></td>
                        <td data-label="Nome"><?php echo esc_html($user['display_name']); ?></td>
                        <td data-label="Stato"><?php echo (int) $user['enabled'] ? 'Attivo' : 'Disabilitato'; ?></td>
                        <td data-label="Azioni"><a href="<?php echo esc_url(admin_url('admin.php?page=fitet-ricevute-users&edit=' . (int) $user['id'])); ?>">Modifica</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$this->repository->users()): ?><tr><td colspan="4">Nessun utente. Crea il primo accesso con il modulo qui sotto.</td></tr><?php endif; ?>
                </tbody>
            </table>
            <h2><?php echo $edit ? 'Modifica utente' : 'Crea utente'; ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fitet_ricevute_save_user">
                <input type="hidden" name="user_id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <?php wp_nonce_field('fitet_ricevute_save_user'); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label for="fr-username">Username</label></th><td><input id="fr-username" name="username" class="regular-text" required minlength="3" maxlength="60" pattern="[a-z0-9][a-z0-9._\-]{2,59}" autocomplete="off" value="<?php echo esc_attr($edit['username'] ?? ''); ?>"><p class="description">Lettere minuscole, numeri, punto, trattino o underscore.</p></td></tr>
                    <tr><th><label for="fr-name">Nome visualizzato</label></th><td><input id="fr-name" name="display_name" class="regular-text" required maxlength="120" value="<?php echo esc_attr($edit['display_name'] ?? ''); ?>"></td></tr>
                    <tr><th><label for="fr-password"><?php echo $edit ? 'Nuova password' : 'Password'; ?></label></th><td><input id="fr-password" name="password" class="regular-text" type="password" minlength="12" maxlength="4096" autocomplete="new-password" <?php echo $edit ? '' : 'required'; ?>><p class="description">Almeno 12 caratteri. <?php echo $edit ? 'Lascia vuoto per conservare la password attuale.' : ''; ?></p></td></tr>
                    <tr><th>Stato</th><td><label><input name="enabled" type="checkbox" value="1" <?php checked(!$edit || (bool) $edit['enabled']); ?>> Utente abilitato</label></td></tr>
                </table>
                <?php submit_button($edit ? 'Salva utente' : 'Crea utente'); ?>
                <?php if ($edit): ?><a href="<?php echo esc_url(admin_url('admin.php?page=fitet-ricevute-users')); ?>">Annulla modifica</a><?php endif; ?>
            </form>
        </div>
        <?php
    }
}
