"""Create an isolated WP installation using a local core and the official SQLite plugin.

Usage: python tests/prepare-runtime.py C:/path/to/wordpress-core
Download/extract sqlite-database-integration into tests/.runtime first.
Never reads or copies the source site's wp-config.php or wp-content.
"""
from pathlib import Path
import shutil
import sys

root = Path(__file__).resolve().parents[1]
runtime = root / 'tests/.runtime'
source = Path(sys.argv[1]).resolve()
wp = runtime / 'wordpress'
wp.mkdir(parents=True, exist_ok=True)
for folder in ('wp-admin', 'wp-includes'):
    shutil.copytree(source / folder, wp / folder, dirs_exist_ok=True)
for item in source.glob('*.php'):
    if (item.name.startswith('wp-') or item.name == 'xmlrpc.php') and item.name not in ('wp-config.php', 'wp-config-sample.php'):
        shutil.copy2(item, wp / item.name)
(wp / 'index.php').write_text("<?php\ndefine('WP_USE_THEMES', true);\nrequire __DIR__ . '/wp-blog-header.php';\n", encoding='utf-8')
content = wp / 'wp-content'
plugins = content / 'plugins'
plugins.mkdir(parents=True, exist_ok=True)
mu = content / 'mu-plugins'
mu.mkdir(exist_ok=True)
shutil.copy2(root / 'tests/mock-extranet.php', mu / 'fitet-test-extranet.php')
sqlite = runtime / 'sqlite-database-integration'
assert (sqlite / 'db.copy').is_file(), 'Extract the official SQLite plugin into tests/.runtime first.'
shutil.copytree(sqlite, plugins / sqlite.name, dirs_exist_ok=True)
shutil.copy2(sqlite / 'db.copy', content / 'db.php')
plugin_target = plugins / 'fitet-ricevute'
for folder in ('includes', 'assets', 'docs', 'vendor'):
    shutil.copytree(root / folder, plugin_target / folder, dirs_exist_ok=True)
for filename in ('fitet-ricevute.php', 'uninstall.php'):
    shutil.copy2(root / filename, plugin_target / filename)
config = """<?php
define('DB_NAME', 'fitet_ricevute_test');
define('DB_USER', 'unused');
define('DB_PASSWORD', 'unused');
define('DB_HOST', 'unused');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
define('WP_HOME', 'http://127.0.0.1:8095');
define('WP_SITEURL', 'http://127.0.0.1:8095');
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', true);
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('AUTH_KEY', 'isolated-test-key-not-for-production');
define('SECURE_AUTH_KEY', 'isolated-test-secure-key-not-for-production');
define('LOGGED_IN_KEY', 'isolated-test-logged-in-key-not-for-production');
define('NONCE_KEY', 'isolated-test-nonce-key-not-for-production');
define('AUTH_SALT', 'isolated-test-auth-salt-not-for-production');
define('SECURE_AUTH_SALT', 'isolated-test-secure-salt-not-for-production');
define('LOGGED_IN_SALT', 'isolated-test-logged-in-salt-not-for-production');
define('NONCE_SALT', 'isolated-test-nonce-salt-not-for-production');
$table_prefix = 'frtest_';
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
"""
(wp / 'wp-config.php').write_text(config, encoding='utf-8')
print(f'Isolated WordPress prepared: {wp}')
