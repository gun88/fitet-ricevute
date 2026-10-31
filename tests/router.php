<?php
// Router for the isolated PHP development server, including a subdirectory WP test.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__ . '/.runtime/wordpress';
if (is_file($root . $path)) { return false; }
require $root . '/index.php';
