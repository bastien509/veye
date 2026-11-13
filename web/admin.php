<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$u = require_admin();
$tab = in_array($_GET['tab'] ?? '', ['settings', 'users', 'reports', 'stats'], true) ? $_GET['tab'] : 'settings';

/* ------------------------------------------------------------ actions */
if (is_post()) {
    if (!csrf_valid()) {
        flash('Your session expired. Please try again.', 'error');
        redirect('admin.php?tab=' . $tab);
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        switch ($action) {
            case 'save_firebase':
                $raw = trim((string) ($_POST['firebase_config'] ?? ''));
                if ($raw !== '') {
                    // Accept the JSON object or the "const firebaseConfig = {...};" snippet from the console.
                    if (preg_match('/\{.*\}/s', $raw, $m)) {
                        $raw = $m[0];
                    }
                    $json = json_decode($raw, true);
                    if (!is_array($json)) {
                        // Console snippet uses unquoted keys: quote them, then parse.
                        $fixed = preg_replace('/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:/', '$1"$2":', $raw);
                        $json = json_decode((string) $fixed, true);
                    }
                    if (!is_array($json) || empty($json['apiKey']) || empty($json['appId']) || empty($json['projectId'])) {
                        throw new AppError('That does not look like a Firebase web config (apiKey, projectId and appId are needed).');
                    }
                    $keep = array_intersect_key($json, array_flip(['apiKey', 'authDomain', 'projectId', 'storageBucket', 'messagingSenderId', 'appId', 'measurementId']));
                    $raw = json_encode($keep, JSON_UNESCAPED_SLASHES);
                }
                set_setting('firebase_config', $raw, (int) $u['id']);
                $site = trim((string) ($_POST['recaptcha_site_key'] ?? ''));
                if ($site !== '' && !preg_match('/^[A-Za-z0-9_-]{20,60}$/', $site)) {
                    throw new AppError('The reCAPTCHA Enterprise site key looks wrong.');
                }
                set_setting('recaptcha_site_key', $site, (int) $u['id']);
                audit((int) $u['id'], 'settings', 'firebase');
                flash('Firebase settings saved.', 'ok');
                break;

            case 'save_service_account':
                $raw = trim((string) ($_POST['service_account'] ?? ''));
                if ($raw === '' && !empty($_POST['clear'])) {
                    set_setting('service_account', '', (int) $u['id']);
                    flash('Service account removed.', 'ok');
                    break;
                }
                $sa = json_decode($raw, true);
                if (!is_array($sa) || ($sa['type'] ?? '') !== 'service_account' || empty($sa['client_email']) || empty($sa['private_key'])) {
                    throw new AppError('Paste the whole service account JSON file (type "service_account").');
                }
                if (!openssl_pkey_get_private($sa['private_key'])) {
                    throw new AppError('The private key in that file could not be read.');
                }
                $keep = array_intersect_key($sa, array_flip(['type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id', 'token_uri']));
                set_setting('service_account', json_encode($keep, JSON_UNESCAPED_SLASHES), (int) $u['id']);
                audit((int) $u['id'], 'settings', 'service_account ' . $sa['client_email']);
                flash('Service account saved (encrypted).', 'ok');
                break;

            case 'test_appcheck':
                require __DIR__ . '/inc/appcheck.php';
                $t = mint_app_check_token();
                flash('App Check works. A token was issued, valid until ' . gmdate('H:i', intdiv((int) $t['expireTimeMillis'], 1000)) . ' UTC.', 'ok');
                break;

            case 'save_ai':
                $model = trim((string) ($_POST['cloud_model'] ?? ''));
                if (!preg_match('/^[a-z0-9.\-]{3,60}$/', $model)) {
                    throw new AppError('Model names look like gemini-3.5-flash-lite.');
                }
                $langs = array_values(array_intersect(array_map('trim', explode(',', strtolower((string) ($_POST['device_languages'] ?? '')))), ['en', 'es', 'fr', 'ht', 'ja']));
                $mode = in_array($_POST['default_mode'] ?? '', ['private', 'smart', 'deep'], true) ? $_POST['default_mode'] : 'smart';
                $rules = trim((string) ($_POST['extra_rules'] ?? '[]'));
                $rj = json_decode($rules === '' ? '[]' : $rules, true);
                if (!is_array($rj)) {
                    throw new AppError('Extra rules must be a JSON array.');
                }
                foreach ($rj as $r) {
                    if (!is_array($r) || empty($r['pattern']) || empty($r['flag']) || @preg_match('/' . str_replace('/', '\/', (string) $r['pattern']) . '/iu', '') === false) {
                        throw new AppError('Each rule needs a valid "pattern" (regex) and a "flag" text.');
                    }
                }
                set_setting('cloud_model', $model, (int) $u['id']);
                set_setting('device_languages', implode(',', $langs ?: ['en']), (int) $u['id']);
                set_setting('default_mode', $mode, (int) $u['id']);
                set_setting('extra_rules', json_encode(array_values($rj), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $u['id']);
                set_setting('reports_auto_approve', !empty($_POST['reports_auto_approve']) ? '1' : '0', (int) $u['id']);
                audit((int) $u['id'], 'settings', 'ai');
                flash('AI routing saved.', 'ok');
                break;

            case 'add_user':
                $name = strtolower(trim((string) ($_POST['username'] ?? '')));
                $pass = (string) ($_POST['password'] ?? '');
                $role = in_array($_POST['role'] ?? '', ['admin', 'user', 'demo'], true) ? $_POST['role'] : 'user';
                if (!preg_match('/^[a-z0-9_.-]{3,40}$/', $name)) {
                    throw new AppError('Usernames are 3–40 letters, digits, dot, dash or underscore.');
                }
                if (strlen($pass) < 8) {
                    throw new AppError('Passwords need at least 8 characters.');
                }
                q('INSERT INTO veye_users (username, display_name, pass_hash, role, daily_limit) VALUES (?, ?, ?, ?, ?)', [
                    $name, mb_substr(trim((string) ($_POST['display_name'] ?? '')) ?: $name, 0, 80),
                    password_hash($pass, PASSWORD_DEFAULT), $role, max(1, min(5000, (int) ($_POST['daily_limit'] ?? 200))),
                ]);
                audit((int) $u['id'], 'add_user', $name . ' ' . $role);
                flash('Account ' . $name . ' created.', 'ok');
                break;

            case 'set_password':
                $id = (int) ($_POST['id'] ?? 0);
                $pass = (string) ($_POST['password'] ?? '');
                if (strlen($pass) < 8) {
                    throw new AppError('Passwords need at least 8 characters.');
                }
                q('UPDATE veye_users SET pass_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
                q('DELETE FROM veye_ext_tokens WHERE user_id = ?', [$id]);
                audit((int) $u['id'], 'set_password', 'user ' . $id);
                flash('Password changed. Extension sign-ins for that account were revoked.', 'ok');
                break;

            case 'toggle_user':
                $id = (int) ($_POST['id'] ?? 0);
                if ($id === (int) $u['id']) {
                    throw new AppError('You cannot disable your own account.');
                }
                q('UPDATE veye_users SET is_active = 1 - is_active WHERE id = ?', [$id]);
                q('DELETE FROM veye_ext_tokens WHERE user_id = ?', [$id]);
                audit((int) $u['id'], 'toggle_user', 'user ' . $id);
                flash('Account updated.', 'ok');
                break;

            case 'reset_usage':
                $id = (int) ($_POST['id'] ?? 0);
                q('DELETE FROM veye_scans WHERE user_id = ? AND created_at >= UTC_DATE()', [$id]);
                flash("Today's usage was reset for that account.", 'ok');
                break;

            case 'report_status':
                $id = (int) ($_POST['id'] ?? 0);
                $st = in_array($_POST['status'] ?? '', ['approved', 'hidden', 'pending'], true) ? $_POST['status'] : 'pending';
                q('UPDATE veye_reports SET status = ? WHERE id = ?', [$st, $id]);
                flash('Report ' . $st . '.', 'ok');
                break;

            default:
                throw new AppError('Unknown action.');
        }
    } catch (AppError $e) {
        flash($e->getMessage(), 'error');
    } catch (PDOException $e) {
        error_log('Veye admin: ' . $e->getMessage());
        flash($e->getCode() === '23000' ? 'That username is taken.' : 'Database error. See the server log.', 'error');
    }
    redirect('admin.php?tab=' . $tab);
}

/* --------------------------------------------------------------- view */
$fb = firebase_config();
$saRaw = setting('service_account');
$sa = $saRaw !== '' ? json_decode($saRaw, true) : null;

page_head('Admin', 'page-admin');
app_header($u, 'admin');
$tabLink = static fn (string $k, string $label): string => '<a class="tab' . ($tab === $k ? ' is-on' : '') . '" href="' . e(url('admin.php?tab=' . $k)) . '">' . e($label) . '</a>';
?>
<main class="wrap">
  <div class="page-title"><h1>Admin</h1></div>
  <nav class="tabs" aria-label="Admin sections">
    <?= $tabLink('settings', 'Keys & AI') ?><?= $tabLink('users', 'Accounts') ?><?= $tabLink('reports', 'Reports') ?><?= $tabLink('stats', 'Stats') ?>
  </nav>
  <?= flash_html() ?>

<?php if ($tab === 'settings'): ?>
  <div class="status-grid">
    <div class="status <?= $fb ? 'ok' : 'todo' ?>"><b>Firebase web app</b><span><?= $fb ? e($fb['projectId']) : 'Not set' ?></span></div>
    <div class="status <?= $sa ? 'ok' : 'todo' ?>"><b>App Check (custom)</b><span><?= $sa ? e($sa['client_email']) : 'Not set' ?></span></div>
    <div class="status <?= setting('recaptcha_site_key') !== '' ? 'ok' : 'opt' ?>"><b>reCAPTCHA Enterprise</b><span><?= setting('recaptcha_site_key') !== '' ? 'Set' : 'Optional' ?></span></div>
  </div>

  <section class="card admin-card">
    <h2>1. Firebase web app config</h2>
    <p class="muted">Firebase console → Project settings → Your apps → Web app → SDK setup and configuration → Config. Paste the object. These values are public by design.</p>
    <form method="post" action="<?= e(url('admin.php?tab=settings')) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_firebase">
      <label class="field"><span>firebaseConfig</span>
        <textarea name="firebase_config" rows="8" class="mono" placeholder='{ "apiKey": "...", "authDomain": "...", "projectId": "...", "appId": "..." }'><?= e($fb ? json_encode($fb, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') ?></textarea>
      </label>
      <label class="field"><span>reCAPTCHA Enterprise site key (optional, for App Check on the website)</span>
        <input name="recaptcha_site_key" class="mono" value="<?= e(setting('recaptcha_site_key')) ?>" maxlength="60">
      </label>
      <button class="btn btn-primary" type="submit">Save Firebase config</button>
    </form>
  </section>

  <section class="card admin-card">
    <h2>2. Service account (App Check for the extension)</h2>
    <p class="muted">Firebase console → Project settings → Service accounts → Generate new private key. Paste the whole JSON file. It is encrypted on the server and never sent to browsers. Veye uses it only to issue App Check tokens to signed-in users.</p>
    <?php if ($sa): ?><p>Current: <code><?= e($sa['client_email']) ?></code> (key id <?= e(substr((string) ($sa['private_key_id'] ?? ''), 0, 8)) ?>…)</p><?php endif; ?>
    <form method="post" action="<?= e(url('admin.php?tab=settings')) ?>" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_service_account">
      <label class="field"><span>Service account JSON</span>
        <textarea name="service_account" rows="6" class="mono" placeholder='{ "type": "service_account", ... }'></textarea>
      </label>
      <div class="row-btns">
        <button class="btn btn-primary" type="submit">Save service account</button>
        <?php if ($sa): ?><button class="btn btn-ghost" type="submit" name="clear" value="1">Remove</button><?php endif; ?>
      </div>
    </form>
    <?php if ($sa && $fb): ?>
      <form method="post" action="<?= e(url('admin.php?tab=settings')) ?>" class="mt">
        <?= csrf_field() ?><input type="hidden" name="action" value="test_appcheck">
        <button class="btn btn-ghost" type="submit">Test App Check token</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="card admin-card">
    <h2>3. AI routing</h2>
    <form method="post" action="<?= e(url('admin.php?tab=settings')) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_ai">
      <div class="grid2">
        <label class="field"><span>Cloud model (fallback and Deep check)</span>
          <input name="cloud_model" class="mono" value="<?= e(setting('cloud_model')) ?>" required>
        </label>
        <label class="field"><span>Languages Gemini Nano answers in (comma list: en, es, fr, ht, ja)</span>
          <input name="device_languages" class="mono" value="<?= e(setting('device_languages')) ?>">
        </label>
      </div>
      <label class="field field-inline"><span>Default privacy mode</span>
        <select name="default_mode">
          <?php foreach (['private' => 'Private (device only)', 'smart' => 'Smart (device first)', 'deep' => 'Deep check (cloud first)'] as $k => $l): ?>
            <option value="<?= e($k) ?>"<?= setting('default_mode') === $k ? ' selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span>Extra red-flag rules (JSON: [{"pattern": "regex", "flag": "text shown", "weight": 20}])</span>
        <textarea name="extra_rules" rows="5" class="mono"><?= e(json_encode(json_decode(setting('extra_rules'), true) ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></textarea>
      </label>
      <label class="check"><input type="checkbox" name="reports_auto_approve" value="1"<?= setting('reports_auto_approve') === '1' ? ' checked' : '' ?>> Publish community reports without review (demo accounts are always reviewed)</label>
      <button class="btn btn-primary" type="submit">Save AI routing</button>
    </form>
  </section>

<?php elseif ($tab === 'users'): ?>
  <?php $users = q("SELECT u.id, u.username, u.display_name, u.role, u.is_active, u.daily_limit, u.last_login_at,
        (SELECT COUNT(*) FROM veye_scans s WHERE s.user_id = u.id AND s.created_at >= UTC_DATE()) AS today
        FROM veye_users u ORDER BY u.id")->fetchAll(); ?>
  <section class="card admin-card">
    <h2>Accounts</h2>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>User</th><th>Role</th><th>Today</th><th>Last sign-in</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $r): ?>
          <tr class="<?= (int) $r['is_active'] ? '' : 'is-off' ?>">
            <td><strong><?= e($r['username']) ?></strong><br><span class="muted"><?= e($r['display_name']) ?></span></td>
            <td><span class="tag"><?= e($r['role']) ?></span></td>
            <td><?= (int) $r['today'] ?> / <?= (int) $r['daily_limit'] ?></td>
            <td><?= e($r['last_login_at'] ?? '—') ?></td>
            <td class="actions">
              <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="set_password"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="password" name="password" placeholder="New password" minlength="8" autocomplete="new-password" required>
                <button class="btn btn-ghost btn-sm" type="submit">Set</button>
              </form>
              <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="reset_usage"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Reset today</button>
              </form>
              <?php if ((int) $r['id'] !== (int) $u['id']): ?>
              <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $r['is_active'] ? 'Disable' : 'Enable' ?></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card admin-card">
    <h2>Add an account</h2>
    <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="grid2" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_user">
      <label class="field"><span>Username</span><input name="username" required maxlength="40"></label>
      <label class="field"><span>Display name</span><input name="display_name" maxlength="80"></label>
      <label class="field"><span>Password</span><input name="password" type="password" minlength="8" required autocomplete="new-password"></label>
      <label class="field"><span>Role</span><select name="role"><option value="user">user</option><option value="demo">demo</option><option value="admin">admin</option></select></label>
      <label class="field"><span>Checks per day</span><input name="daily_limit" type="number" min="1" max="5000" value="200"></label>
      <div class="field"><span>&nbsp;</span><button class="btn btn-primary" type="submit">Create account</button></div>
    </form>
  </section>

<?php elseif ($tab === 'reports'): ?>
  <?php $reps = q("SELECT r.*, u.username FROM veye_reports r LEFT JOIN veye_users u ON u.id = r.user_id ORDER BY (r.status = 'pending') DESC, r.created_at DESC LIMIT 100")->fetchAll(); ?>
  <section class="reports">
    <?php if (!$reps): ?><p class="muted">No reports yet.</p><?php endif; ?>
    <?php foreach ($reps as $r): ?>
      <article class="card report">
        <div class="report-top">
          <span class="tag tag-<?= e($r['status']) ?>"><?= e($r['status']) ?></span>
          <span class="verdict-chip v-<?= e($r['verdict']) ?>"><span class="dot"></span><?= e($r['verdict']) ?> · <?= (int) $r['risk'] ?>%</span>
          <span class="tag"><?= e($r['scam_type']) ?></span>
          <?php if ($r['domain'] !== ''): ?><span class="tag tag-domain"><?= e($r['domain']) ?></span><?php endif; ?>
          <span class="muted">by <?= e($r['username'] ?? '?') ?> · <?= e($r['created_at']) ?></span>
        </div>
        <p class="snippet">“<?= e($r['snippet']) ?>”</p>
        <div class="row-btns">
          <?php foreach (['approved' => 'Approve', 'hidden' => 'Hide'] as $st => $label): if ($r['status'] === $st) { continue; } ?>
            <form method="post" action="<?= e(url('admin.php?tab=reports')) ?>" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="report_status"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="<?= e($st) ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= e($label) ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

<?php else: ?>
  <?php
  $by = q("SELECT source, client, COUNT(*) n, ROUND(AVG(ms)) avg_ms FROM veye_scans WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY GROUP BY source, client ORDER BY n DESC")->fetchAll();
  $days = q("SELECT DATE(created_at) d, COUNT(*) n, SUM(source = 'on_device') dev FROM veye_scans WHERE created_at >= UTC_DATE() - INTERVAL 13 DAY GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
  ?>
  <section class="card admin-card">
    <h2>Where the AI ran (last 30 days)</h2>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Source</th><th>Client</th><th>Checks</th><th>Avg time</th></tr></thead>
        <tbody>
        <?php foreach ($by as $r): ?>
          <tr><td><?= e($r['source']) ?></td><td><?= e($r['client']) ?></td><td><?= (int) $r['n'] ?></td><td><?= (int) $r['avg_ms'] ?> ms</td></tr>
        <?php endforeach; ?>
        <?php if (!$by): ?><tr><td colspan="4" class="muted">No checks yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card admin-card">
    <h2>Last 14 days</h2>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Day</th><th>Checks</th><th>On device</th></tr></thead>
        <tbody>
        <?php foreach ($days as $r): ?>
          <tr><td><?= e($r['d']) ?></td><td><?= (int) $r['n'] ?></td><td><?= (int) $r['dev'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$days): ?><tr><td colspan="3" class="muted">No checks yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
</main>
<?php
page_foot();
