<?php
// Logs one check's outcome (no text, no screenshot) and enforces the daily limit.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

require_api_post();
[$u, $client] = api_user();
$in = json_body();

$used = (int) q('SELECT COUNT(*) FROM veye_scans WHERE user_id = ? AND created_at >= UTC_DATE()', [$u['id']])->fetchColumn();
if ($used >= (int) $u['daily_limit']) {
    json_out(['error' => 'daily_limit', 'used' => $used], 429);
}

$pick = static fn ($v, array $ok, $def) => in_array($v, $ok, true) ? $v : $def;
$source  = $pick($in['source'] ?? '', ['on_device', 'in_cloud', 'rules_only'], 'rules_only');
$verdict = $pick($in['verdict'] ?? '', ['safe', 'suspicious', 'scam'], 'suspicious');
$mode    = $pick($in['mode'] ?? '', ['private', 'smart', 'deep'], 'smart');
$lang    = $pick($in['lang'] ?? '', ['en', 'es', 'fr', 'ht'], 'en');
$type    = preg_replace('/[^a-z_]/', '', strtolower((string) ($in['scamType'] ?? 'none'))) ?: 'none';
$client  = $client === 'extension' ? 'extension' : $pick($in['client'] ?? 'web', ['web', 'android'], 'web');

q(
    'INSERT INTO veye_scans (user_id, client, source, mode, verdict, risk, scam_type, lang, ms, had_image, redacted_items)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        $u['id'], $client, $source, $mode, $verdict,
        max(0, min(100, (int) ($in['risk'] ?? 0))), substr($type, 0, 40), $lang,
        max(0, min(600000, (int) ($in['ms'] ?? 0))), empty($in['hadImage']) ? 0 : 1,
        max(0, min(999, (int) ($in['redacted'] ?? 0))),
    ]
);
json_out(['ok' => true, 'used' => $used + 1, 'limit' => (int) $u['daily_limit']]);
