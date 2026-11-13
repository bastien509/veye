<?php
/**
 * Firebase App Check custom provider, server side.
 *
 * Chrome extension pages cannot run reCAPTCHA, so signed-in Veye clients ask
 * this server for an App Check token. The server signs a custom token with the
 * Firebase service account (stored encrypted in veye_settings) and exchanges it
 * through the App Check REST API, like the Admin SDK's appCheck().createToken().
 */
declare(strict_types=1);

defined('VEYE') || exit;

const VEYE_APPCHECK_AUD = 'https://firebaseappcheck.googleapis.com/google.firebase.appcheck.v1.TokenExchangeService';

function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function service_account(): array
{
    $sa = json_decode(setting('service_account'), true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
        throw new AppError('Service account is not set.');
    }
    return $sa;
}

function sign_jwt(array $claims, string $privateKey): string
{
    $head = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $body = b64url(json_encode($claims, JSON_UNESCAPED_SLASHES));
    $sig = '';
    if (!openssl_sign($head . '.' . $body, $sig, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new AppError('Could not sign with the service account key.');
    }
    return $head . '.' . $body . '.' . b64url($sig);
}

function http_post_json(string $url, array $body, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES),
    ]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode($raw, true);
    return [$code, is_array($j) ? $j : []];
}

/** OAuth access token for the service account, cached for its lifetime. */
function google_access_token(array $sa): string
{
    $cacheFile = __DIR__ . '/gat-' . substr(hash('sha256', $sa['client_email']), 0, 12) . '.cache';
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) {
        $c = json_decode(unseal($cached), true);
        if (is_array($c) && ($c['exp'] ?? 0) > time() + 120) {
            return (string) $c['token'];
        }
    }
    $now = time();
    $assertion = sign_jwt([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/firebase',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ], $sa['private_key']);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion]),
    ]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode($raw, true);
    if ($code !== 200 || empty($j['access_token'])) {
        error_log('Veye OAuth token failed: HTTP ' . $code . ' ' . substr($raw, 0, 300));
        throw new AppError('Google sign-in for the service account failed.');
    }
    @file_put_contents($cacheFile, seal(json_encode(['token' => $j['access_token'], 'exp' => $now + (int) ($j['expires_in'] ?? 3600)])));
    return (string) $j['access_token'];
}

/** Returns ['token' => ..., 'expireTimeMillis' => ...] for the configured Firebase web app. */
function mint_app_check_token(): array
{
    $fb = firebase_config();
    if (!$fb) {
        throw new AppError('Firebase is not configured.');
    }
    $sa = service_account();
    $now = time();
    $custom = sign_jwt([
        'iss'    => $sa['client_email'],
        'sub'    => $sa['client_email'],
        'aud'    => VEYE_APPCHECK_AUD,
        'iat'    => $now,
        'exp'    => $now + 300,
        'app_id' => $fb['appId'],
    ], $sa['private_key']);

    $project = rawurlencode((string) ($fb['projectId']));
    $app = rawurlencode((string) $fb['appId']);
    [$code, $j] = http_post_json(
        "https://firebaseappcheck.googleapis.com/v1/projects/$project/apps/$app:exchangeCustomToken",
        ['customToken' => $custom],
        ['Authorization: Bearer ' . google_access_token($sa)]
    );
    if ($code !== 200 || empty($j['token'])) {
        error_log('Veye App Check exchange failed: HTTP ' . $code . ' ' . substr(json_encode($j), 0, 300));
        throw new AppError('App Check token exchange failed.');
    }
    $ttl = (int) rtrim((string) ($j['ttl'] ?? '3600s'), 's');
    return ['token' => $j['token'], 'expireTimeMillis' => ($now + max(60, $ttl)) * 1000];
}
