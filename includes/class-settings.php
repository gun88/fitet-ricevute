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

    public static function save_configuration(array $input,array $files): void {
        global $wpdb;
        $settings=self::sanitize($input['settings']??[]);
        $credentials=FR_Extranet_Credentials::prepare(is_array($input['extranet']??null)?$input['extranet']:[]);
        if(is_wp_error($credentials))throw new RuntimeException($credentials->get_error_message());
        $signature=FR_Signature::prepare_upload($files['signature_file']??[],!empty($input['clear_signature']));
        $stamp=FR_Signature::prepare_upload($files['stamp_file']??[],!empty($input['clear_stamp']),'stamp');
        $values=[self::OPTION=>$settings,FR_Extranet_Credentials::OPTION=>$credentials,FR_Signature::OPTION=>$signature,FR_Signature::STAMP_OPTION=>$stamp];
        if($wpdb->query('START TRANSACTION')===false)throw new RuntimeException('Salvataggio non disponibile.');
        try {
            foreach($values as $key=>$value){if($value===null)delete_option($key);else update_option($key,$value,false);if(get_option($key,null)!==$value)throw new RuntimeException('Salvataggio configurazione non riuscito.');}
            if($wpdb->query('COMMIT')===false)throw new RuntimeException('Salvataggio configurazione non riuscito.');
        }catch(Throwable $error){$wpdb->query('ROLLBACK');foreach(array_keys($values) as $key)wp_cache_delete($key,'options');wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');throw $error;}
    }

    public static function public_config(): array {
        $all = self::all();
        return array_intersect_key($all, array_flip(['app_name', 'society_name', 'login_message', 'enabled']));
    }
}
