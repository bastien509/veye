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
  <div class="page-title">
    <h1>Veye for Chrome</h1>
    <p class="section-lead">A side panel that checks the page you're on, or any text you select. The page is scored on your computer with local rules and Gemini Nano. The cloud is used only if your Chrome has no local model, and it gets redacted text.</p>
  </div>
  <div class="grid2 grid-top">
    <section class="card">
      <h2>Install (2 minutes)</h2>
      <ol class="howto">
        <li><?php if ($zipReady): ?><a class="btn btn-primary btn-sm" href="<?= e(url('download/veye-extension.zip')) ?>?v=<?= e((string) filemtime($zip)) ?>" download>Download veye-extension.zip</a><?php else: ?>The download is being prepared.<?php endif; ?> and unzip it.</li>
        <li>Open <code>chrome://extensions</code> and turn on <strong>Developer mode</strong> (top right).</li>
        <li>Click <strong>Load unpacked</strong> and pick the unzipped <code>veye-extension</code> folder.</li>
        <li>Click the Veye icon in the toolbar. The side panel opens; sign in with your Veye account (judges: the demo account below).</li>
        <li>For on-device AI, enable <code>chrome://flags/#prompt-api-for-gemini-nano-multimodal-input</code> and restart Chrome.</li>
      </ol>
      <?= demo_card() ?>
    </section>
    <section class="card">
      <h2>What it does</h2>
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
