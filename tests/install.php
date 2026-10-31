<?php
// Test fixture only; run from CLI, never ship with the installable plugin.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8095';
$_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/.runtime/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', function () { return new WP_Error('test_network_disabled', 'Network disabled in test.'); });
echo "WordPress core loaded.\n";
if (!is_blog_installed()) {
    wp_install('FITET Ricevute test', 'wp-test-admin', 'admin@example.invalid', true, '', 'WP-test-only-2026!');
}
echo "WordPress tables ready.\n";
if (!username_exists('wp-test-admin')) {
    $admin_id = wp_create_user('wp-test-admin', 'WP-test-only-2026!', 'admin@example.invalid');
    if (is_wp_error($admin_id)) { throw new RuntimeException($admin_id->get_error_message()); }
    (new WP_User($admin_id))->set_role('administrator');
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_clean_plugins_cache();
echo 'Plugin directory: ' . WP_PLUGIN_DIR . "\n";
echo 'Found plugins: ' . implode(', ', array_keys(get_plugins())) . "\n";
$result = activate_plugin('fitet-ricevute/fitet-ricevute.php');
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
require_once WP_PLUGIN_DIR . '/fitet-ricevute/fitet-ricevute.php';
update_option('permalink_structure', '/%postname%/');
FR_Frontend::rewrite();
flush_rewrite_rules(false);
$repo = new FR_Repository();
$user = $repo->user_by_username('fr-tester');
$result = $repo->save_user((int) ($user['id'] ?? 0), [
    'username' => 'fr-tester', 'display_name' => 'Operatore test',
    'password' => 'FR-test-only-2026!', 'enabled' => true,
]);
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
echo "Installed test WordPress $wp_version and FITET Ricevute.\n";
