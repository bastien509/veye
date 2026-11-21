<?php
/**
 * Veye bootstrap: config, PDO, schema install, session, CSRF, auth (session
 * and extension bearer tokens), settings (secrets encrypted at rest),
 * rate limiting, security headers and helpers.
 */
declare(strict_types=1);

defined('VEYE') || define('VEYE', 1);

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'bootstrap.php') {
    http_response_code(404);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('UTC');

$GLOBALS['VEYE_CFG'] = (static function (): array {
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
        http_response_code(503);
        exit('Veye is not configured yet.');
    }
    return require $file;
})();

const VEYE_ASSET_VER = '5';
const VEYE_SCHEMA_VER = '1';

/** Settings an admin can change, with their defaults. Secret ones never leave the server. */
const VEYE_SETTINGS = [
    'firebase_config'    => ['default' => '', 'secret' => false],
    'recaptcha_site_key' => ['default' => '', 'secret' => false],
    'service_account'    => ['default' => '', 'secret' => true],
    'cloud_model'        => ['default' => 'gemini-3.5-flash-lite', 'secret' => false],
    'device_languages'   => ['default' => 'en,es', 'secret' => false],
    'default_mode'       => ['default' => 'smart', 'secret' => false],
    'extra_rules'        => ['default' => '[]', 'secret' => false],
    'reports_auto_approve' => ['default' => '0', 'secret' => false],
];

final class AppError extends RuntimeException
{
}

function cfg(string $path, $default = null)
{
    $node = $GLOBALS['VEYE_CFG'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $default;
        }
        $node = $node[$key];
    }
    return $node;
}

/* ---------------------------------------------------------------- DB */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('db.host'), (int) cfg('db.port', 3306), cfg('db.name'));
    try {
        $pdo = new PDO($dsn, (string) cfg('db.user'), (string) cfg('db.pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('Veye DB connect failed: ' . $e->getMessage());
        http_response_code(503);
        exit('The service is not available right now.');
    }
    $pdo->exec("SET time_zone = '+00:00'");
    install_schema($pdo);
    return $pdo;
}

/** Creates the tables and seed accounts once; a lock file skips the work afterwards. */
function install_schema(PDO $pdo): void
{
    $lock = __DIR__ . '/schema-v' . VEYE_SCHEMA_VER . '.lock';
    if (is_file($lock)) {
        return;
    }
    $sql = (string) file_get_contents(dirname(__DIR__) . '/schema.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
    // Seed accounts come from the server-only config as password_hash() values, never plain passwords.
    foreach ((array) cfg('seed_users', []) as $seed) {
        if (empty($seed['username']) || empty($seed['pass_hash'])) {
            continue;
        }
        $st = $pdo->prepare('INSERT IGNORE INTO veye_users (username, display_name, pass_hash, role, daily_limit) VALUES (?, ?, ?, ?, ?)');
        $st->execute([
            strtolower($seed['username']),
            $seed['display_name'] ?? $seed['username'],
            $seed['pass_hash'],
            in_array($seed['role'] ?? 'user', ['admin', 'user', 'demo'], true) ? $seed['role'] : 'user',
            (int) ($seed['daily_limit'] ?? 200),
        ]);
    }
    @file_put_contents($lock, gmdate('c'));
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function audit(?int $userId, string $action, string $detail = ''): void
{
    q('INSERT INTO veye_audit (user_id, action, detail) VALUES (?, ?, ?)', [$userId, substr($action, 0, 40), mb_substr($detail, 0, 255)]);
}

/* ----------------------------------------------------------- settings */

function secret_key(): string
{
    $k = base64_decode((string) cfg('secret_key', ''), true);
    if ($k === false || strlen($k) < 32) {
        throw new AppError('secret_key missing in config');
    }
    return substr(hash('sha256', $k, true), 0, 32);
}

function seal(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $ct);
}

function unseal(string $sealed): string
{
    if (strncmp($sealed, 'v1:', 3) !== 0) {
        return '';
    }
    $raw = base64_decode(substr($sealed, 3), true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

function setting(string $key): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT k, v, is_secret FROM veye_settings')->fetchAll() as $r) {
            $cache[$r['k']] = (int) $r['is_secret'] === 1 ? unseal($r['v']) : $r['v'];
        }
    }
    if (!isset(VEYE_SETTINGS[$key])) {
        throw new AppError('unknown setting ' . $key);
    }
    return array_key_exists($key, $cache) ? (string) $cache[$key] : (string) VEYE_SETTINGS[$key]['default'];
}

function set_setting(string $key, string $value, int $byUser): void
{
    if (!isset(VEYE_SETTINGS[$key])) {
        throw new AppError('unknown setting ' . $key);
    }
    $secret = VEYE_SETTINGS[$key]['secret'];
    $stored = ($secret && $value !== '') ? seal($value) : $value;
    q(
        'INSERT INTO veye_settings (k, v, is_secret, updated_by) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE v = VALUES(v), is_secret = VALUES(is_secret), updated_by = VALUES(updated_by)',
        [$key, $stored, $secret ? 1 : 0, $byUser]
    );
}

/** Parsed Firebase web config, or null when the admin has not added it yet. */
function firebase_config(): ?array
{
    $j = json_decode(setting('firebase_config'), true);
    return (is_array($j) && !empty($j['apiKey']) && !empty($j['appId']) && !empty($j['projectId'])) ? $j : null;
}

/** Everything the browser and extension may see. No secrets here. */
function public_client_config(): array
{
    $fb = firebase_config();
    $rules = json_decode(setting('extra_rules'), true);
    $appCheck = 'none';
    if ($fb) {
        if (setting('service_account') !== '') {
            $appCheck = 'custom';
        } elseif (setting('recaptcha_site_key') !== '') {
            $appCheck = 'recaptcha';
        }
    }
    return [
        'firebase'         => $fb,
        'appCheck'         => $appCheck,
        'recaptchaSiteKey' => setting('recaptcha_site_key'),
        'cloudModel'       => setting('cloud_model'),
        'deviceLanguages'  => array_values(array_filter(array_map('trim', explode(',', setting('device_languages'))))),
        'defaultMode'      => setting('default_mode'),
        'extraRules'       => is_array($rules) ? $rules : [],
    ];
}

/* ------------------------------------------------------------ request */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
}

/** The site root path (e.g. /veye), also from pages inside api/. */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('#/api$#', '', $dir);
    return rtrim((string) $dir, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $file): string
{
    return url('assets/' . $file) . '?v=' . VEYE_ASSET_VER;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // Firebase AI Logic, App Check, Installations and Remote Config endpoints; reCAPTCHA when it is used.
    $google = 'https://*.googleapis.com https://www.google.com https://www.gstatic.com https://www.recaptcha.net';
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; "
        . "style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
        . "script-src 'self' https://www.google.com https://www.gstatic.com https://www.recaptcha.net; "
        . "frame-src https://www.google.com https://www.recaptcha.net; "
        // data: lets Firebase AI Logic hand a screenshot to Gemini Nano on-device (it converts images with fetch(data:...)).
        . "connect-src 'self' data: $google; form-action 'self'; frame-ancestors 'none'; "
        . "base-uri 'self'; object-src 'none'"
    );
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
}

/* ------------------------------------------------------------ session */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('VEYESID');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path'     => base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 7));
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $sent = null): bool
{
    $sent = $sent ?? (string) ($_POST['csrf'] ?? '');
    return $sent !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

function flash(string $msg, string $kind = 'info'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'kind' => $kind];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* --------------------------------------------------------------- auth */

function find_active_user(int $id): ?array
{
    $u = q('SELECT id, username, display_name, role, daily_limit FROM veye_users WHERE id = ? AND is_active = 1', [$id])->fetch();
    return $u ?: null;
}

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    $id = (int) ($_SESSION['uid'] ?? 0);
    if ($id > 0) {
        $cache = find_active_user($id);
        if (!$cache) {
            unset($_SESSION['uid']);
        }
    }
    return $cache;
}

function require_user(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_user();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Admins only.');
    }
    return $u;
}

/** Checks a username + password; spends the same time whether or not the user exists. */
function verify_login(string $username, string $password): ?array
{
    $row = q('SELECT id, pass_hash, is_active FROM veye_users WHERE username = ?', [strtolower(trim($username))])->fetch();
    $hash = $row['pass_hash'] ?? '$2y$10$lrLTC4JWPGJ66OhsUQeth.8noyM9PvKw6pq1BZeSyPalYePzvm1Su';
    $ok = password_verify($password, $hash);
    if (!$row || !$ok || (int) $row['is_active'] !== 1) {
        return null;
    }
    return find_active_user((int) $row['id']);
}

function login_as(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('UPDATE veye_users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
}

function logout(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

/* --------------------------------------------------------- rate limit */

function rate_limit(string $bucket, int $max, int $windowSec): bool
{
    $window = intdiv(time(), $windowSec) * $windowSec;
    $key = sha1($bucket . '|' . $windowSec);
    q('INSERT INTO veye_rate_limits (bucket, window_start, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1', [$key, $window]);
    $hits = (int) q('SELECT hits FROM veye_rate_limits WHERE bucket = ? AND window_start = ?', [$key, $window])->fetchColumn();
    if (random_int(1, 300) === 1) {
        q('DELETE FROM veye_rate_limits WHERE window_start < ?', [time() - 90000]);
    }
    return $hits <= $max;
}

/* ---------------------------------------------------------------- JSON API */

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 65536);
    $j = json_decode((string) $raw, true);
    return is_array($j) ? $j : [];
}

/**
 * API caller: a bearer token (Chrome extension) or a signed-in browser
 * session that also sends the X-CSRF header. Returns [user, client].
 */
function api_user(): array
{
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+([A-Za-z0-9_\-]{40,100})$/', $auth, $m)) {
        $row = q(
            'SELECT t.id, t.user_id FROM veye_ext_tokens t WHERE t.token_hash = ? AND t.expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $m[1])]
        )->fetch();
        $u = $row ? find_active_user((int) $row['user_id']) : null;
        if (!$u) {
            json_out(['error' => 'signed_out'], 401);
        }
        q('UPDATE veye_ext_tokens SET last_used_at = UTC_TIMESTAMP() WHERE id = ?', [$row['id']]);
        return [$u, 'extension'];
    }
    $u = current_user();
    if (!$u) {
        json_out(['error' => 'signed_out'], 401);
    }
    if (!is_post() || !csrf_valid((string) ($_SERVER['HTTP_X_CSRF'] ?? ''))) {
        json_out(['error' => 'bad_csrf'], 403);
    }
    return [$u, 'web'];
}

function require_api_post(): void
{
    if (!is_post()) {
        json_out(['error' => 'method'], 405);
    }
}

if (PHP_SAPI !== 'cli') {
    send_security_headers();
    start_session();
}
