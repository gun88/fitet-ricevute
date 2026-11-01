<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

// Preserve general settings and accounts. Revoke sessions and remove external credentials.
global $wpdb;
$table = $wpdb->prefix . 'fitet_ricevute_sessions';
$wpdb->query("DELETE FROM $table");
wp_clear_scheduled_hook('fitet_ricevute_cleanup_sessions');
delete_option('fitet_ricevute_extranet_credentials');

delete_option('fitet_ricevute_signature');
