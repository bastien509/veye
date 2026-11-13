<?php
// App Check custom provider: signed-in clients get a short-lived App Check token.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/appcheck.php';

require_api_post();
[$u] = api_user();
if (!rate_limit('ac|u' . $u['id'], 30, 3600) || !rate_limit('ac|ip' . client_ip(), 60, 3600)) {
    json_out(['error' => 'rate_limited'], 429);
}
try {
    json_out(['ok' => true] + mint_app_check_token());
} catch (AppError $e) {
    json_out(['error' => 'app_check_unavailable', 'message' => 'App Check is not set up yet.'], 503);
}
