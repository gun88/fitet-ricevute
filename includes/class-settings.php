<?php

defined('ABSPATH') || exit;

final class FR_Settings {
    public const OPTION = 'fitet_ricevute_settings';

    public static function defaults(): array {
        return [
            'app_name' => 'FITET Ricevute',
            'society_name' => '',
            'society_code' => '',
            'login_message' => 'Accedi con le credenziali fornite dalla tua società.',
            'enabled' => true,
            'session_hours' => 12,
            'remember_days' => 30,
        ];
    }

    public static function all(): array {
        return wp_parse_args(get_option(self::OPTION, []), self::defaults());
    }

    public static function sanitize($input): array {
        $input = is_array($input) ? $input : [];
        return [
            'app_name' => sanitize_text_field($input['app_name'] ?? '') ?: 'FITET Ricevute',
            'society_name' => sanitize_text_field($input['society_name'] ?? ''),
            'society_code' => preg_replace('/[^0-9]/', '', (string) ($input['society_code'] ?? '')),
            'login_message' => sanitize_textarea_field($input['login_message'] ?? ''),
            'enabled' => !empty($input['enabled']),
            'session_hours' => max(1, min(72, (int) ($input['session_hours'] ?? 12))),
            'remember_days' => max(1, min(90, (int) ($input['remember_days'] ?? 30))),
        ];
    }

    public static function public_config(): array {
        $all = self::all();
        return array_intersect_key($all, array_flip(['app_name', 'society_name', 'login_message', 'enabled']));
    }
}
