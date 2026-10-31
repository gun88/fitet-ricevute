<?php
// Backend credential lifecycle tests, restricted to the isolated CLI environment.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['HTTP_HOST'] = '127.0.0.1:8095';
$_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/.runtime/wordpress/wp-load.php';
$previous = get_option(FR_Extranet_Credentials::OPTION, null);
$checks = 0;
function verify_extranet(bool $result, string $message): void {
    global $checks;
    if (!$result) { throw new RuntimeException($message); }
    $checks++;
    echo 'PASS ' . $message . "\n";
}
try {
    delete_option(FR_Extranet_Credentials::OPTION);
    verify_extranet(is_wp_error(FR_Extranet_Credentials::for_sync()), 'missing credentials produce an explicit backend error');
    $password = '  Test-FITeT\\"<>&-2026!  ';
    verify_extranet(FR_Extranet_Credentials::save(['username' => 'Societa.TEST', 'password' => $password]) === true, 'credentials save successfully');
    $stored = get_option(FR_Extranet_Credentials::OPTION);
    verify_extranet(str_starts_with($stored['password_encrypted'], 'v1:') && !str_contains(serialize($stored), $password), 'database contains encrypted password, not plaintext');
    verify_extranet(FR_Extranet_Credentials::for_sync() === ['username' => 'Societa.TEST', 'password' => $password], 'server recovers exact password, including spaces and special characters');
    FR_Extranet_Credentials::save(['username' => 'Societa.TEST', 'password' => '']);
    verify_extranet(get_option(FR_Extranet_Credentials::OPTION) === $stored, 'blank password preserves existing ciphertext');
    verify_extranet(FR_Extranet_Credentials::status() === ['username' => 'Societa.TEST', 'password_saved' => true], 'admin status contains no password or ciphertext');
    verify_extranet(!str_contains(serialize(FR_Settings::public_config()), 'Societa.TEST') && !str_contains(serialize(FR_Settings::public_config()), 'password'), 'public configuration excludes credentials');
    FR_Extranet_Credentials::save(['username' => 'Societa.TEST', 'password' => 'Replacement-test-only!']);
    verify_extranet(FR_Extranet_Credentials::for_sync()['password'] === 'Replacement-test-only!', 'new password replaces stored secret');
    verify_extranet(is_wp_error(FR_Extranet_Credentials::save(['username' => [], 'password' => 'bad'])), 'malformed credentials are rejected');
    verify_extranet(is_wp_error(FR_Extranet_Credentials::save(['username' => 'Societa.TEST', 'password' => 'new', 'clear_password' => true])), 'conflicting replace/remove request is rejected');
    $tampered = get_option(FR_Extranet_Credentials::OPTION);
    $data = base64_decode(substr($tampered['password_encrypted'], 3), true);
    $data[28] = chr(ord($data[28]) ^ 1);
    $tampered['password_encrypted'] = 'v1:' . base64_encode($data);
    update_option(FR_Extranet_Credentials::OPTION, $tampered, false);
    verify_extranet(is_wp_error(FR_Extranet_Credentials::for_sync()), 'tampered encrypted password is rejected');
    FR_Extranet_Credentials::save(['username' => 'Societa.TEST', 'clear_password' => true]);
    verify_extranet(!FR_Extranet_Credentials::status()['password_saved'] && is_wp_error(FR_Extranet_Credentials::for_sync()), 'explicit removal clears the secret');
    global $wpdb;
    $autoload = $wpdb->get_var($wpdb->prepare("SELECT autoload FROM $wpdb->options WHERE option_name = %s", FR_Extranet_Credentials::OPTION));
    verify_extranet(in_array($autoload, ['no', 'off', 'auto-off'], true), 'private credentials are not autoloaded');
    echo "$checks credential checks passed.\n";
} finally {
    if ($previous === null) { delete_option(FR_Extranet_Credentials::OPTION); }
    else { update_option(FR_Extranet_Credentials::OPTION, $previous, false); }
}
