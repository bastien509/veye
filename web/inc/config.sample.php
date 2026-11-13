<?php
// Copy to config.php on the server. config.php is denied by inc/.htaccess and
// ignored by git. Never put these values in JS, HTML or error text.
defined('VEYE') || exit;

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'DB_NAME',
        'user' => 'DB_USER',
        'pass' => 'DB_PASSWORD',
    ],
    // 32+ random bytes, base64. Encrypts secret settings (the Firebase service account) at rest.
    'secret_key' => 'BASE64_RANDOM_32_BYTES',
    // Accounts created once when the tables are installed. password_hash() values only.
    'seed_users' => [
        ['username' => 'admin', 'display_name' => 'Veye Admin', 'role' => 'admin', 'pass_hash' => '', 'daily_limit' => 1000],
        ['username' => 'demo', 'display_name' => 'Demo Judge', 'role' => 'demo', 'pass_hash' => '', 'daily_limit' => 300],
    ],
];
