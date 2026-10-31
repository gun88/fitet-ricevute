<?php
// CLI fixture controls for revocation/expiry tests. Never expose over HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['HTTP_HOST'] = '127.0.0.1:8095';
$_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/.runtime/wordpress/wp-load.php';
$repo = new FR_Repository();
$user = $repo->user_by_username('fr-tester');
switch ($argv[1] ?? '') {
    case 'expire':
        $wpdb->query('UPDATE ' . FR_Repository::sessions_table() . ' SET expires_at = 1');
        break;
    case 'disable':
    case 'enable':
        $repo->save_user((int) $user['id'], [
            'username' => $user['username'], 'display_name' => $user['display_name'],
            'enabled' => $argv[1] === 'enable',
        ]);
        break;
    case 'reset-password':
        $repo->save_user((int) $user['id'], [
            'username' => $user['username'], 'display_name' => $user['display_name'],
            'enabled' => true, 'password' => 'FR-test-only-2026!',
        ]);
        break;
    case 'portal-off':
    case 'portal-on':
        update_option(FR_Settings::OPTION, array_merge(FR_Settings::all(), ['enabled' => $argv[1] === 'portal-on']));
        break;
    case 'clear-rate-limit':
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_fr_login_%' OR option_name LIKE '_transient_timeout_fr_login_%'");
        break;
    default:
        throw new RuntimeException('Unknown test control');
}
