<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$u = current_user();
$stats = q(
    "SELECT COUNT(*) AS total,
            SUM(source = 'on_device') AS on_device,
            SUM(source = 'in_cloud') AS in_cloud,
            SUM(verdict = 'scam') AS scams
       FROM veye_scans"
)->fetch() ?: [];
$total = (int) ($stats['total'] ?? 0);
$aiTotal = (int) ($stats['on_device'] ?? 0) + (int) ($stats['in_cloud'] ?? 0);
$devicePct = $aiTotal > 0 ? (int) round(100 * (int) $stats['on_device'] / $aiTotal) : 0;

page_head('Scam shield that thinks on your device', 'page-landing');
app_header($u);
?>
<main>
  <section class="hero">
    <div class="wrap hero-in">
      <div class="hero-copy">
        <p class="eyebrow">Veye · "watch out" in Haitian Kreyòl</p>
        <h1>Is this a scam? Ask your own device first.</h1>
        <p class="lead">Veye reads suspicious messages, pages and screenshots with Gemini Nano <strong>on your device</strong>, so your bank pages, chats and codes never leave it. Only when your device can't answer does Veye ask the cloud, and then it sends redacted text only.</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e(url($u ? 'app.php' : 'login.php')) ?>">Check a message</a>
          <a class="btn btn-ghost" href="<?= e(url('extension.php')) ?>">Get the Chrome extension</a>
        </div>
        <?= demo_card() ?>
      </div>
      <div class="hero-demo" aria-hidden="true">
        <div class="sms">
          <div class="sms-from">+1 (305) ••• ••42</div>
          <p>URGENT: Your remittance of $480 to Haiti is ON HOLD. Confirm your card and the 6-digit code at moneyrelease-help.co within 30 min or it is cancelled.</p>
        </div>
        <div class="verdict-chip v-scam"><span class="dot"></span> Scam · 94% risk</div>
        <ul class="mini-flags">
          <li>Asks for a one-time code</li>
          <li>30-minute deadline pressure</li>
          <li>Look-alike payment domain</li>
        </ul>
        <div class="ran-on ran-device">Ran on this device · 0 bytes sent · 410 ms</div>
      </div>
    </div>
  </section>

  <section class="band">
    <div class="wrap">
      <h2>Three layers, one answer</h2>
      <p class="section-lead">Veye uses the Firebase AI Logic hybrid SDK. The same prompt runs on Gemini Nano when the device has it and on Gemini in the cloud when it doesn't.</p>
      <div class="steps3">
        <article class="layer">
          <span class="layer-n">1</span>
          <h3>Rules, instantly</h3>
          <p>Known red flags (code requests, gift cards, deadlines, look-alike links) in English, Kreyòl, French and Spanish. Runs offline in milliseconds.</p>
        </article>
        <article class="layer">
          <span class="layer-n">2</span>
          <h3>Gemini Nano, on device</h3>
          <p>Reads the whole message or screenshot and explains it in plain words. Private by design: nothing is uploaded, nothing costs money.</p>
        </article>
        <article class="layer">
          <span class="layer-n">3</span>
          <h3>Cloud, only when needed</h3>
          <p>No local model, a language Nano doesn't speak yet (Kreyòl), or a deep check you asked for. Emails, phones, cards and codes are removed first.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="band band-alt">
    <div class="wrap grid2">
      <div>
        <h2>Who Veye is for</h2>
        <ul class="ticks">
          <li><strong>Families sending money home.</strong> Fake transfer holds and "release fees" target remittances to Haiti and Latin America.</li>
          <li><strong>Parents and grandparents</strong> who get "your grandson is in jail" calls and texts.</li>
          <li><strong>People new to online banking</strong> who can't tell a real bank page from a copy.</li>
          <li><strong>Job seekers</strong> offered work that starts with a "training fee".</li>
        </ul>
      </div>
      <div>
        <h2>Why on device matters here</h2>
        <p>A tool that reads every page you open is only acceptable if it keeps what it reads. Veye's extension scores pages with local rules and Gemini Nano, so your inbox and bank pages stay on your computer.</p>
        <div class="stat-row">
          <div class="stat"><b><?= number_format($total) ?></b><span>checks so far</span></div>
          <div class="stat"><b><?= $aiTotal > 0 ? $devicePct . '%' : '—' ?></b><span>of AI checks stayed on device</span></div>
          <div class="stat"><b><?= number_format((int) ($stats['scams'] ?? 0)) ?></b><span>scams caught</span></div>
        </div>
      </div>
    </div>
  </section>

  <section class="band">
    <div class="wrap">
      <h2>Try it in 2 minutes</h2>
      <ol class="howto">
        <li>Use Chrome 139 or newer on a desktop. For on-device AI, open <code>chrome://flags/#prompt-api-for-gemini-nano-multimodal-input</code>, set it to Enabled and restart Chrome.</li>
        <li>Sign in with the demo account above and open <a href="<?= e(url('app.php')) ?>">Check</a>. Pick a sample scam or paste your own.</li>
        <li>Watch the badge: <span class="ran-on ran-device inline-badge">On device</span> or <span class="ran-on ran-cloud inline-badge">Cloud · redacted</span>. No Gemini Nano? Veye falls back to the cloud on its own.</li>
        <li>Install the <a href="<?= e(url('extension.php')) ?>">Chrome extension</a> to check any page or selected text.</li>
      </ol>
    </div>
  </section>
</main>
<?php
page_foot();
