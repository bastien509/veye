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

/** Stroke icons (24px grid). */
function icon(string $name, string $class = ''): string
{
    static $p = [
        'shield'  => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'cpu'     => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6" rx="1"/><path d="M15 2v2M15 20v2M2 15h2M2 9h2M20 15h2M20 9h2M9 2v2M9 20v2"/>',
        'cloud'   => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
        'bolt'    => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
        'lock'    => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'eye'     => '<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/>',
        'browser' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 6.5h.01M10 6.5h.01"/>',
        'users'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'chart'   => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 16v-4M12 16V8M17 16v-7"/>',
        'check'   => '<path d="M20 6 9 17l-5-5"/>',
        'arrow'   => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'copy'    => '<rect x="8" y="8" width="14" height="14" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
        'sparkle' => '<path d="M12 3l1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2z"/>',
        'wifioff' => '<path d="M12 20h.01M8.5 16.43a5 5 0 0 1 7 0M2 8.82a15 15 0 0 1 4.17-2.65M10.66 5c4.01-.36 8.14.9 11.34 3.76M16.85 11.25a10 10 0 0 1 2.22 1.68M5 13a10 10 0 0 1 5.24-2.76M2 2l20 20"/>',
        'image'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
        'lang'    => '<path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6"/>',
        'code'    => '<path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>',
        'github'  => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.4 5.4 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/>',
        'message' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'alert'   => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
        'moon'    => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
        'upload'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'route'   => '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
        'settings'=> '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu'    => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'download'=> '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'clock'   => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'send'    => '<path d="M14.54 21.69a.5.5 0 0 0 .94-.03l6.5-19a.5.5 0 0 0-.64-.64l-19 6.5a.5.5 0 0 0-.03.94l7.93 3.18a2 2 0 0 1 1.11 1.11z"/><path d="m21.85 2.15-10.94 10.94"/>',
        'heart'   => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
        'briefcase'=> '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect x="2" y="6" width="20" height="14" rx="2"/>',
        'home'    => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    ];
    return '<svg class="ico ' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

function page_head(string $title, string $bodyClass = '', string $description = ''): void
{
    $desc = $description !== '' ? $description : 'Veye checks scams on your device first. Hybrid AI with Firebase AI Logic and Gemini Nano: private on device, cloud only when needed, with personal data removed first.';
    ?><!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#111112" media="(prefers-color-scheme: dark)">
<meta property="og:title" content="Veye · The scam shield that thinks on your device">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<title><?= e($title) ?> · Veye</title>
<link rel="icon" href="<?= e(asset('logo.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<script src="<?= e(asset('theme.js')) ?>"></script>
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

function app_header(?array $u, string $active = ''): void
{
    $link = static function (string $key, string $href, string $label, string $ico) use ($active): string {
        return '<a class="nav-link' . ($active === $key ? ' is-on' : '') . '" href="' . e(url($href)) . '"'
            . ($active === $key ? ' aria-current="page"' : '') . '>' . icon($ico) . e($label) . '</a>';
    };
    $initial = $u ? strtoupper(mb_substr((string) $u['display_name'], 0, 1)) : '';
    ?>
<header class="site-head" id="siteHead">
  <div class="wrap">
    <div class="bar">
      <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="Veye home"><?= logo_svg() ?><span class="brand-txt">Veye<small>Scam shield · hybrid AI</small></span></a>
      <nav class="nav" id="mainNav" aria-label="Main">
        <?= $link('check', 'app.php', 'Check', 'shield') ?>
        <?= $link('community', 'community.php', 'Community', 'users') ?>
        <?= $link('extension', 'extension.php', 'Extension', 'browser') ?>
        <?php if ($u && $u['role'] === 'admin'): ?><?= $link('admin', 'admin.php', 'Admin', 'settings') ?><?php endif; ?>
      </nav>
      <div class="head-actions">
        <span class="nano-chip" id="nanoChip" title="Is Gemini Nano available in this browser?"><span class="pulse"></span><span class="nano-txt">Checking Gemini Nano…</span></span>
        <button type="button" class="icon-btn" id="themeBtn" aria-label="Switch light or dark theme" title="Theme"><?= icon('moon', 'ico-moon') ?><?= icon('sun', 'ico-sun') ?></button>
        <?php if ($u): ?>
          <span class="user-pill" title="Signed in as <?= e($u['username']) ?>"><span class="avatar"><?= e($initial) ?></span><span class="who"><?= e($u['display_name']) ?></span></span>
          <form method="post" action="<?= e(url('logout.php')) ?>" class="inline">
            <?= csrf_field() ?>
            <button class="icon-btn" type="submit" aria-label="Sign out" title="Sign out"><?= icon('logout') ?></button>
          </form>
        <?php else: ?>
          <a class="btn btn-dark btn-sm" href="<?= e(url('login.php')) ?>">Sign in <?= icon('arrow', 'ico-sm') ?></a>
        <?php endif; ?>
        <button type="button" class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="mainNav"><?= icon('menu') ?></button>
      </div>
    </div>
  </div>
</header>
<?php
}

function page_foot(array $scripts = []): void
{
    ?>
<footer class="foot">
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-brand">
        <a class="brand" href="<?= e(url('index.php')) ?>"><?= logo_svg() ?><span class="brand-txt">Veye<small>"Watch out" in Haitian Kreyòl</small></span></a>
        <p>A scam shield that asks your own device first. Built with Firebase AI Logic hybrid inference for #BuildHybridAI 2026.</p>
      </div>
      <div>
        <h4>Product</h4>
        <ul>
          <li><a href="<?= e(url('app.php')) ?>">Check a message</a></li>
          <li><a href="<?= e(url('extension.php')) ?>">Chrome extension</a></li>
          <li><a href="<?= e(url('community.php')) ?>">Community warnings</a></li>
        </ul>
      </div>
      <div>
        <h4>How it works</h4>
        <ul>
          <li><a href="<?= e(url('index.php#router')) ?>">Hybrid router</a></li>
          <li><a href="<?= e(url('index.php#privacy')) ?>">Privacy</a></li>
          <li><a href="<?= e(url('index.php#judges')) ?>">Try it in 2 minutes</a></li>
        </ul>
      </div>
      <div>
        <h4>Open source</h4>
        <ul>
          <li><a href="https://github.com/bastien509/veye" rel="noopener">GitHub repository</a></li>
          <li><a href="https://github.com/bastien509/veye/blob/main/LICENSE" rel="noopener">Apache-2.0 license</a></li>
          <li><a href="https://firebase.google.com/docs/ai-logic" rel="noopener">Firebase AI Logic</a></li>
        </ul>
      </div>
    </div>
    <div class="foot-bottom">
      <span><span class="flag-bar"><i></i><i></i></span>Made for our people, in Kreyòl and English.</span>
      <span>Veye never stores what you check.</span>
    </div>
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

function copy_row(string $label, string $value): string
{
    return '<div class="cred-row"><div><span>' . e($label) . '</span><br><code>' . e($value) . '</code></div>'
        . '<button type="button" class="copy-btn" data-copy="' . e($value) . '">' . icon('copy', 'ico-sm') . 'Copy</button></div>';
}

/** Shown on the landing and login pages so judges can test right away. */
function demo_card(bool $withButton = false): string
{
    return '<div class="demo-card"><strong>' . icon('sparkle', 'ico-sm') . 'Judges and testers</strong>'
        . '<span class="muted">Use the shared demo account:</span>'
        . copy_row('Username', 'demo') . copy_row('Password', 'VeyeDemo509')
        . ($withButton ? '<a class="btn btn-dark btn-block" href="' . e(url('login.php')) . '">Sign in with the demo account ' . icon('arrow', 'ico-sm') . '</a>' : '')
        . '</div>';
}
