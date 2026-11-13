<?php
// Chrome extension sign-in: returns a bearer token (only its hash is stored).
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

require_api_post();
$in = json_body();
$name = (string) ($in['username'] ?? '');
if (!rate_limit('extlogin|ip' . client_ip(), 10, 900) || !rate_limit('extlogin|n' . strtolower($name), 10, 900)) {
    json_out(['error' => 'rate_limited'], 429);
}
$u = verify_login($name, (string) ($in['password'] ?? ''));
if (!$u) {
    json_out(['error' => 'bad_login'], 401);
}
$token = rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
q(
    'INSERT INTO veye_ext_tokens (user_id, token_hash, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 60 DAY)',
    [$u['id'], hash('sha256', $token)]
);
q('UPDATE veye_users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$u['id']]);
audit((int) $u['id'], 'ext_login');
json_out(['ok' => true, 'token' => $token, 'user' => ['name' => $u['display_name'], 'role' => $u['role']]]);
