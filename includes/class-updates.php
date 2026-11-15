<?php
defined('ABSPATH') || exit;

final class FR_Updates {
    public const METADATA_URL='https://raw.githubusercontent.com/gun88/fitet-ricevute/refs/heads/main/release/plugin.json';
    private static $checker;
    public static function init(string $pluginFile) {
        if(self::$checker)return self::$checker;
        require_once __DIR__.'/plugin-update-checker/plugin-update-checker.php';
        return self::$checker=\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(self::METADATA_URL,$pluginFile,'fitet-ricevute');
    }
}
