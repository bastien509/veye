<?php
// Community report: only the redacted snippet, the domain and the red flags are kept.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

require_api_post();
[$u] = api_user();
if (!rate_limit('rep|u' . $u['id'], 20, 86400)) {
    json_out(['error' => 'rate_limited'], 429);
}
$in = json_body();

$verdict = in_array($in['verdict'] ?? '', ['safe', 'suspicious', 'scam'], true) ? $in['verdict'] : 'suspicious';
$type = preg_replace('/[^a-z_]/', '', strtolower((string) ($in['scamType'] ?? 'other'))) ?: 'other';
$domain = strtolower((string) ($in['domain'] ?? ''));
$domain = preg_match('/^[a-z0-9.-]{1,190}$/', $domain) ? $domain : '';
$snippet = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($in['snippet'] ?? ''))), 0, 400);
$flags = array_slice(array_map(static fn ($f) => mb_substr(trim((string) $f), 0, 120), (array) ($in['redFlags'] ?? [])), 0, 6);
$lang = in_array($in['lang'] ?? '', ['en', 'es', 'fr', 'ht'], true) ? $in['lang'] : 'en';

// The client already redacts; the server strips anything that still looks personal.
$snippet = (string) preg_replace(
    ['/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '/\+?\d[\d\s().-]{6,}\d/', '/\b\d{4,}\b/'],
    ['[EMAIL]', '[PHONE]', '[NUMBER]'],
    $snippet
);

if ($snippet === '' && $domain === '') {
    json_out(['error' => 'empty'], 422);
}
$status = (setting('reports_auto_approve') === '1' && $u['role'] !== 'demo') ? 'approved' : 'pending';
q(
    'INSERT INTO veye_reports (user_id, scam_type, verdict, risk, domain, snippet, red_flags, lang, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$u['id'], substr($type, 0, 40), $verdict, max(0, min(100, (int) ($in['risk'] ?? 0))), $domain, $snippet, implode("\n", $flags), $lang, $status]
);
json_out(['ok' => true, 'status' => $status]);
