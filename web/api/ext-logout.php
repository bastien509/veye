<?php
// Revokes the calling extension token.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

require_api_post();
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (preg_match('/^Bearer\s+([A-Za-z0-9_\-]{40,100})$/', $auth, $m)) {
    q('DELETE FROM veye_ext_tokens WHERE token_hash = ?', [hash('sha256', $m[1])]);
}
json_out(['ok' => true]);
