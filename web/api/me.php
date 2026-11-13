<?php
// Who is signed in, and today's usage.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

require_api_post();
[$u] = api_user();
$used = (int) q('SELECT COUNT(*) FROM veye_scans WHERE user_id = ? AND created_at >= UTC_DATE()', [$u['id']])->fetchColumn();
json_out(['ok' => true, 'user' => ['name' => $u['display_name'], 'role' => $u['role']], 'used' => $used, 'limit' => (int) $u['daily_limit']]);
