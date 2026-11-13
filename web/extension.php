<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$u = current_user();
$zip = __DIR__ . '/download/veye-extension.zip';
$zipReady = is_file($zip);

page_head('Chrome extension', 'page-ext');
app_header($u, 'extension');
?>
<main class="wrap">
  <div class="ext-hero">
    <div>
      <span class="kicker">Chrome extension</span>
      <h1 class="page-hero-title">Every page, checked <span class="serif">on your computer.</span></h1>
      <p class="lead">A side panel that checks the page you're on, or any text you select. Offline rules score every page, and Gemini Nano reads it when you ask. The cloud is used only if your Chrome has no local model, and it gets redacted text.</p>
      <div class="hero-cta">
        <?php if ($zipReady): ?><a class="btn btn-primary btn-lg" href="<?= e(url('download/veye-extension.zip')) ?>?v=<?= e((string) filemtime($zip)) ?>" download><?= icon('download') ?>Download the extension</a><?php endif; ?>
        <a class="btn btn-ghost btn-lg" href="https://github.com/bastien509/veye/tree/main/extension" rel="noopener"><?= icon('github') ?>Source code</a>
      </div>
    </div>
    <div class="ext-shot" aria-hidden="true">
      <div class="ext-page">
        <div class="mini-browser"><div class="mb-bar"><span class="mb-url">westernunion-release.help/verify</span><span class="mb-badge"><?= logo_svg('logo logo-sm') ?></span></div></div>
        <span class="mb-line w80"></span><span class="mb-line"></span><span class="mb-line w60"></span>
        <div class="fake-form"><i></i><i></i><span class="mb-warn">This page asks for card details</span></div>
      </div>
      <div class="ext-panel">
        <strong>Veye</strong>
        <div class="ring t-scam" id="extRing"><b>91<small>%</small></b></div>
        <span class="verdict-chip v-scam"><span class="dot"></span>Scam</span>
        <span class="muted">Look-alike of Western Union asking for a card number.</span>
        <span class="ran-on ran-device">On device · 0 bytes</span>
      </div>
    </div>
  </div>
  <div class="grid2 grid-top">
    <section class="card">
      <h2 class="panel-title"><?= icon('download') ?>Install in 2 minutes</h2>
      <p></p>
      <ol class="howto">
        <li><span><?php if ($zipReady): ?><a href="<?= e(url('download/veye-extension.zip')) ?>?v=<?= e((string) filemtime($zip)) ?>" download>Download veye-extension.zip</a><?php else: ?>The download is being prepared.<?php endif; ?> and unzip it.</span></li>
        <li><span>Open <code>chrome://extensions</code> and turn on <strong>Developer mode</strong> (top right).</span></li>
        <li><span>Click <strong>Load unpacked</strong> and pick the unzipped <code>veye-extension</code> folder.</span></li>
        <li><span>Click the Veye icon in the toolbar. The side panel opens; sign in with your Veye account (judges: the demo account below).</span></li>
        <li><span>For on-device AI, enable <code>chrome://flags/#prompt-api-for-gemini-nano-multimodal-input</code> and restart Chrome.</span></li>
      </ol>
      <?= demo_card() ?>
    </section>
    <section class="card">
      <h2 class="panel-title"><?= icon('sparkle') ?>What it does</h2>
      <p></p>
      <ul class="ticks">
        <li><strong>Badge on every page</strong> from local rules only. No AI call, no network, no data sent.</li>
        <li><strong>Check this page</strong> in the side panel: Gemini Nano reads it on your computer.</li>
        <li><strong>Right-click selected text</strong> → "Check with Veye".</li>
        <li><strong>Password and card forms</strong> on new or look-alike domains get a warning.</li>
        <li>Same privacy modes as the web app: Private, Smart, Deep check.</li>
      </ul>
    </section>
  </div>
</main>
<?php
page_foot();
