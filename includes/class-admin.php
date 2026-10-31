<?php

defined('ABSPATH') || exit;

final class FR_Admin {
    public function __construct(private FR_Repository $repository) {}

    public function init(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_action('admin_enqueue_scripts', [$this, 'styles']);
        add_action('admin_post_fitet_ricevute_save_user', [$this, 'save_user']);
        add_action('admin_post_fitet_ricevute_save_extranet', [$this, 'save_extranet']);
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
        $fields = [
            'app_name' => ['Nome del portale', 'text'],
            'society_name' => ['Nome società', 'text'],
            'society_code' => ['Codice società FITeT', 'text'],
            'session_hours' => ['Durata sessione (ore, da 1 a 72)', 'number'],
            'remember_days' => ['Durata “Ricordami” (giorni, da 1 a 90)', 'number'],
        ];
        ?>
        <div class="wrap fr-admin">
            <h1>FITET Ricevute — Configurazione</h1>
            <p><a class="button" href="<?php echo esc_url(home_url('/fitet-ricevute/')); ?>" target="_blank" rel="noopener">Apri il portale</a></p>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php settings_fields('fitet_ricevute'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Portale attivo</th><td><label>
                        <input name="<?php echo esc_attr(FR_Settings::OPTION); ?>[enabled]" type="checkbox" value="1" <?php checked($config['enabled']); ?>> Consenti l’accesso al portale
                    </label></td></tr>
                    <?php foreach ($fields as $key => [$label, $type]): ?>
                        <tr><th scope="row"><label for="fr-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td><input class="regular-text" id="fr-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(FR_Settings::OPTION . '[' . $key . ']'); ?>" type="<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($config[$key]); ?>" <?php echo $type === 'number' ? 'min="1" max="' . ($key === 'session_hours' ? '72' : '90') . '"' : ''; ?>></td></tr>
                    <?php endforeach; ?>
                    <tr><th scope="row"><label for="fr-login-message">Messaggio nella pagina di accesso</label></th><td>
                        <textarea class="large-text" rows="3" id="fr-login-message" name="<?php echo esc_attr(FR_Settings::OPTION); ?>[login_message]"><?php echo esc_textarea($config['login_message']); ?></textarea>
                    </td></tr>
                </table>
                <?php submit_button('Salva impostazioni'); ?>
            </form>
            <h2>Extranet FITeT</h2>
            <p>Credenziali della società per la ricerca automatica delle ricevute nell’extranet. I PDF vengono scaricati dai link pubblici.</p>
            <?php if (isset($_GET['extranet_saved'])): ?><div class="notice notice-success"><p>Credenziali extranet FITeT salvate.</p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fitet_ricevute_save_extranet">
                <?php wp_nonce_field('fitet_ricevute_save_extranet'); ?>
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
                <?php submit_button('Salva credenziali FITeT'); ?>
            </form>
            <h2>Funzionalità del portale</h2>
            <p>Sincronizza ricevute · Modifica prezzi tornei · Crea rimborsi</p>
            <p>Il portale gestisce ricevute, iscrizioni, prezzi, schede giocatore e ricevute di rimborso.</p>
        </div>
        <?php
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
