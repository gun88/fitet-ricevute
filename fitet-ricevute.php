<?php
/**
 * Plugin Name: FITET Ricevute
 * Description: Ricevute FITeT, dati dei tornei e degli iscritti, prezzi e rimborsi.
 * Version: 0.10.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: tpomante
 * License: GPL-2.0-or-later
 * Text Domain: fitet-ricevute
 */

defined('ABSPATH') || exit;

define('FITET_RICEVUTE_VERSION', '0.10.0');
define('FITET_RICEVUTE_DIR', plugin_dir_path(__FILE__));
define('FITET_RICEVUTE_URL', plugin_dir_url(__FILE__));

if (is_file(FITET_RICEVUTE_DIR . 'vendor/autoload.php')) { require_once FITET_RICEVUTE_DIR . 'vendor/autoload.php'; }

require_once FITET_RICEVUTE_DIR . 'includes/class-settings.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-extranet-credentials.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-receipt-source.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-receipt-parser.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-data-repository.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-extranet-client.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-sync-service.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-repository.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-auth.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-signature.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-refund-workflow.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-api.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-admin.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-backup.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-backup-admin.php';
require_once FITET_RICEVUTE_DIR . 'includes/class-frontend.php';

register_activation_hook(__FILE__, function () {
    FR_Repository::install();
    FR_Frontend::rewrite();
    flush_rewrite_rules();
    if (!wp_next_scheduled('fitet_ricevute_cleanup_sessions')) {
        wp_schedule_event(time(), 'daily', 'fitet_ricevute_cleanup_sessions');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('fitet_ricevute_cleanup_sessions');
    flush_rewrite_rules();
});

add_action('plugins_loaded', function () {
    if (get_option('fitet_ricevute_schema') !== FITET_RICEVUTE_VERSION) {
        FR_Repository::install();
    }
    $repository = new FR_Repository();
    $auth = new FR_Auth($repository);
    (new FR_Api($auth, new FR_Data_Repository()))->init();
    (new FR_Admin($repository))->init();
    (new FR_Backup_Admin())->init();
    (new FR_Frontend())->init();
    add_action('fitet_ricevute_cleanup_sessions', [$repository, 'cleanup_sessions']);
});
