<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

if (current_user()) {
    redirect('app.php');
}

$error = '';
if (is_post()) {
    $name = (string) ($_POST['username'] ?? '');
    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!rate_limit('login|ip' . client_ip(), 10, 900) || !rate_limit('login|n' . strtolower($name), 10, 900)) {
        $error = 'Too many tries. Wait 15 minutes and try again.';
    } else {
        $u = verify_login($name, (string) ($_POST['password'] ?? ''));
        if ($u) {
            login_as($u);
            audit((int) $u['id'], 'login');
            redirect($u['role'] === 'admin' ? 'admin.php' : 'app.php');
        }
        $error = 'Wrong username or password.';
    }
}

page_head('Sign in', 'page-auth');
app_header(null);
?>
<main class="wrap auth-wrap">
  <form class="card auth-card" method="post" action="<?= e(url('login.php')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <h1>Sign in to Veye</h1>
    <?php if ($error !== ''): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <label class="field">
      <span>Username</span>
      <input name="username" autocomplete="username" required maxlength="40" value="<?= e($_POST['username'] ?? '') ?>">
    </label>
    <label class="field">
      <span>Password</span>
      <input name="password" type="password" autocomplete="current-password" required maxlength="200">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    <?= demo_card() ?>
  </form>
</main>
<?php
page_foot();
