<?php
// Public client config for the web app and the Chrome extension. No secrets.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

header('Cache-Control: public, max-age=60');
json_out(['ok' => true, 'config' => public_client_config()]);
