<?php
declare(strict_types=1);

defined('VEYE') || exit;

function logo_svg(string $class = 'logo'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 48 48" aria-hidden="true">'
        . '<path d="M24 3 6 9.5v12.8C6 33.6 13.6 41.8 24 45c10.4-3.2 18-11.4 18-22.7V9.5L24 3Z" fill="#00209F"/>'
        . '<path d="M24 3v42c10.4-3.2 18-11.4 18-22.7V9.5L24 3Z" fill="#D21034"/>'
        . '<path d="M11 24c3.6-5.6 8-8.4 13-8.4s9.4 2.8 13 8.4c-3.6 5.6-8 8.4-13 8.4S14.6 29.6 11 24Z" fill="#fff"/>'
        . '<circle cx="24" cy="24" r="5.4" fill="#F5C518"/><circle cx="24" cy="24" r="2.3" fill="#111418"/>'
        . '</svg>';
}

function page_head(string $title, string $bodyClass = '', string $description = ''): void
{
    $desc = $description !== '' ? $description : 'Veye checks scams on your device first. Hybrid AI with Firebase AI Logic and Gemini Nano: private on-device, cloud only when needed.';
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#111418" media="(prefers-color-scheme: dark)">
<meta property="og:title" content="Veye · Scam shield that thinks on your device">
<meta property="og:description" content="<?= e($desc) ?>">
<title><?= e($title) ?> · Veye</title>
<link rel="icon" href="<?= e(asset('logo.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<script src="<?= e(asset('theme.js')) ?>"></script>
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

function app_header(?array $u, string $active = ''): void
{
    $link = static function (string $key, string $href, string $label) use ($active): string {
        return '<a class="nav-link' . ($active === $key ? ' is-on' : '') . '" href="' . e(url($href)) . '">' . e($label) . '</a>';
    };
    ?>
<header class="topbar">
  <div class="wrap topbar-in">
    <a class="brand" href="<?= e(url('index.php')) ?>"><?= logo_svg() ?><span>Veye</span></a>
    <nav class="top-nav" aria-label="Main">
      <?= $link('check', 'app.php', 'Check') ?>
      <?= $link('community', 'community.php', 'Community') ?>
      <?= $link('extension', 'extension.php', 'Extension') ?>
      <?php if ($u && $u['role'] === 'admin'): ?><?= $link('admin', 'admin.php', 'Admin') ?><?php endif; ?>
    </nav>
    <div class="top-actions">
      <button type="button" class="icon-btn" id="themeBtn" aria-label="Change theme" title="Theme">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9A7 7 0 0 1 12 3Z" fill="currentColor"/></svg>
      </button>
      <?php if ($u): ?>
        <span class="who" title="Signed in"><?= e($u['display_name']) ?></span>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="inline">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
        </form>
      <?php else: ?>
        <a class="btn btn-primary btn-sm" href="<?= e(url('login.php')) ?>">Sign in</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php
}

function page_foot(array $scripts = []): void
{
    ?>
<footer class="foot">
  <div class="wrap foot-in">
    <span><?= logo_svg('logo logo-sm') ?> Veye · built for #BuildHybridAI with Firebase AI Logic</span>
    <span class="muted">Veye never stores what you check. Open source (Apache-2.0).</span>
  </div>
</footer>
<script src="<?= e(asset('app.js')) ?>" defer></script>
<?php foreach ($scripts as $s): ?>
<script type="module" src="<?= e(asset($s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

function flash_html(): string
{
    $html = '';
    foreach (take_flashes() as $f) {
        $html .= '<div class="flash flash-' . e($f['kind']) . '" role="status">' . e($f['msg']) . '</div>';
    }
    return $html;
}

/** Shown on the landing and login pages so judges can test right away. */
function demo_card(): string
{
    return '<div class="demo-card"><strong>Judges and testers</strong>'
        . '<span>Username <code>demo</code> · Password <code>VeyeDemo509</code></span></div>';
}
