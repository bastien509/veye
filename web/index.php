<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$u = current_user();
$stats = q(
    "SELECT COUNT(*) AS total,
            SUM(source = 'on_device') AS on_device,
            SUM(source = 'in_cloud') AS in_cloud,
            SUM(verdict = 'scam') AS scams,
            SUM(redacted_items) AS redacted,
            SUM(source = 'in_cloud' AND had_image = 1) AS images_sent
       FROM veye_scans"
)->fetch() ?: [];
$total = (int) ($stats['total'] ?? 0);
// Cloud calls only ever get redacted text; only a screenshot the user agreed to send leaves as-is.
$keptPct = $total > 0 ? (int) floor(100 * ($total - (int) $stats['images_sent']) / $total) : null;
$checkUrl = url($u ? 'app.php' : 'login.php');

page_head('The scam shield that thinks on your device', 'page-landing');
app_header($u);
?>
<main>
  <!-- ============================================================ HERO -->
  <section class="hero">
    <span class="hero-glow-gold" aria-hidden="true"></span>
    <div class="wrap hero-in">
      <div class="hero-copy">
        <span class="eyebrow"><b>NEW</b> Hybrid AI with Firebase AI Logic + Gemini Nano</span>
        <h1>Is this a scam? Ask your <span class="serif">own device</span> first.</h1>
        <p class="lead">Veye reads suspicious texts, pages and screenshots with Gemini Nano <strong>on your device</strong>, so your bank pages, chats and codes never leave it. When your device can't answer, Veye asks the cloud, with your personal details <strong>removed first</strong>.</p>
        <div class="hero-cta">
          <a class="btn btn-primary btn-lg" href="<?= e($checkUrl) ?>"><?= icon('shield') ?>Check a message</a>
          <a class="btn btn-ghost btn-lg" href="<?= e(url('extension.php')) ?>"><?= icon('browser') ?>Get the Chrome extension</a>
        </div>
        <div class="hero-proof">
          <span><?= icon('check') ?>Nothing you check is stored</span>
          <span><?= icon('check') ?>Works offline</span>
          <span><?= icon('check') ?>English · Kreyòl · Français · Español</span>
        </div>
      </div>

      <div class="demo-stage" aria-label="Example: Veye flags a fake money-transfer text on the device">
        <span class="float-chip float-1"><?= icon('cpu') ?>Gemini Nano · on device</span>
        <span class="float-chip float-2"><?= icon('lock') ?>0 bytes sent</span>
        <span class="float-chip float-3"><?= icon('code') ?><code>PREFER_ON_DEVICE</code></span>
        <div class="device">
          <div class="device-bar"><i></i><i></i><i></i><span class="device-url"><?= icon('lock') ?>petiht.com/veye</span></div>
          <div class="device-body">
            <div class="sms">
              <span class="scanline" aria-hidden="true"></span>
              <div class="sms-from"><span>+1 (305) ••• ••42</span><span>now</span></div>
              <p><span class="mark d1">URGENT</span>: Your remittance of $480 to Haiti is <span class="mark d2">ON HOLD</span>. Confirm your card and the <span class="mark d3">6-digit code</span> at <span class="mark d4">moneyrelease-help.co</span> within 30 min or it is cancelled.</p>
            </div>
            <div class="verdict-panel">
              <div class="ring ring-demo t-scam"><b>94<small>%</small></b></div>
              <div>
                <span class="verdict-chip v-scam"><span class="dot"></span>Scam · fake transfer notice</span>
                <ul>
                  <li><?= icon('alert') ?>Asks for a one-time code</li>
                  <li><?= icon('alert') ?>30-minute deadline pressure</li>
                  <li><?= icon('alert') ?>Look-alike payment domain</li>
                </ul>
              </div>
            </div>
            <div class="demo-foot">
              <span class="ran-on ran-device">On this device · 0 bytes sent · 410 ms</span>
              <span class="pill-mono">inferenceSource: on_device</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================ TECH STRIP -->
  <div class="strip">
    <div class="wrap strip-in">
      <span>Built on</span>
      <span class="tech"><?= icon('sparkle') ?>Firebase AI Logic</span>
      <span class="tech"><?= icon('cpu') ?>Gemini Nano</span>
      <span class="tech"><?= icon('cloud') ?>Gemini 3.5 Flash-Lite</span>
      <span class="tech"><?= icon('browser') ?>Chrome Prompt API</span>
      <span class="tech"><?= icon('shield') ?>App Check</span>
      <span class="tech"><?= icon('settings') ?>Remote Config</span>
    </div>
  </div>

  <!-- ============================================================ ROUTER -->
  <section class="section" id="router">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="kicker">The hybrid router</span>
        <h2>Three layers. <span class="serif">One honest answer.</span></h2>
        <p class="section-lead">Every check goes through the same pipeline. Veye picks where the AI runs, one check at a time, and shows you why.</p>
      </div>
      <div class="router reveal">
        <span class="router-flow" aria-hidden="true"></span>
        <article class="node n-rules">
          <span class="node-step">01</span>
          <div class="node-ico"><?= icon('bolt') ?></div>
          <h3>Offline rules</h3>
          <p>Red flags like code requests, gift cards, deadlines and look-alike links, in four languages. Runs in milliseconds with no network.</p>
          <div class="node-meta"><span class="tag">~2 ms</span><span class="tag">0 bytes</span><span class="tag">works offline</span></div>
        </article>
        <article class="node n-device">
          <span class="node-step">02</span>
          <div class="node-ico"><?= icon('cpu') ?></div>
          <h3>Gemini Nano, on device</h3>
          <p>Reads the whole message or screenshot and explains it in plain words, with structured JSON output. Private by design and free to run.</p>
          <div class="node-meta"><span class="tag">ONLY / PREFER_ON_DEVICE</span><span class="tag">0 bytes</span></div>
        </article>
        <article class="node n-cloud">
          <span class="node-step">03</span>
          <div class="node-ico"><?= icon('cloud') ?></div>
          <h3>Cloud, only when needed</h3>
          <p>Same prompt, same schema, through Firebase AI Logic. Emails, phone numbers, card numbers and codes are removed on the device first.</p>
          <div class="node-meta"><span class="tag">PREFER_IN_CLOUD</span><span class="tag">redacted only</span></div>
        </article>
      </div>
      <div class="routes reveal">
        <div class="route"><b>No local model</b><span>The SDK falls back to the cloud with redacted text.</span><br><span class="pill-mono">PREFER_ON_DEVICE → cloud</span></div>
        <div class="route"><b>Answer in Kreyòl</b><span>Nano doesn't speak it yet, so Veye routes to the cloud.</span><br><span class="pill-mono">PREFER_IN_CLOUD</span></div>
        <div class="route"><b>Offline</b><span>Forced on device. With no model, the rules still answer.</span><br><span class="pill-mono">ONLY_ON_DEVICE</span></div>
        <div class="route"><b>Screenshot to the cloud</b><span>Never sent without your yes; it can't be redacted.</span><br><span class="pill-mono">consent first</span></div>
      </div>
    </div>
  </section>

  <!-- ============================================================ BENTO -->
  <section class="section section-alt" id="privacy">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="kicker">Built for privacy</span>
        <h2>Reading every page is only okay <span class="serif">if it stays with you.</span></h2>
      </div>
      <div class="bento">
        <article class="tile w4 reveal">
          <div class="tile-ico"><?= icon('lock') ?></div>
          <h3>Personal data is removed before anything can leave</h3>
          <p>Veye removes personal details on the device. The wording, amounts and link domains stay, because those are what show the scam.</p>
          <div class="tile-visual">
            <div class="redact-demo"><span class="k">// what the cloud receives</span><br>Your transfer is on hold. Text <span class="r">[PHONE]</span> or email <span class="r">[EMAIL]</span>.<br>Confirm card <span class="r">[CARD]</span> and code <span class="r">[CODE]</span> at https://moneyrelease-help.co/…</div>
          </div>
        </article>
        <article class="tile reveal">
          <div class="tile-ico"><?= icon('eye') ?></div>
          <h3>We keep nothing</h3>
          <p>No message text. No screenshots.</p>
          <div class="tile-visual"><div class="big-zero">0<small>bytes of your content stored</small></div></div>
        </article>
        <article class="tile reveal">
          <div class="tile-ico"><?= icon('route') ?></div>
          <h3>You choose the mode</h3>
          <p>Private, Smart or Deep check, for each message.</p>
          <div class="tile-visual">
            <div class="modes-mini">
              <div><?= icon('lock') ?>Private<small>device</small></div>
              <div><?= icon('cpu') ?>Smart<small>device first</small></div>
              <div><?= icon('cloud') ?>Deep<small>cloud first</small></div>
            </div>
          </div>
        </article>
        <article class="tile reveal">
          <div class="tile-ico"><?= icon('lang') ?></div>
          <h3>Kreyòl first</h3>
          <p>Answers in the language people actually read.</p>
          <div class="tile-visual"><div class="kreyol-bubble"><small>Veye · Kreyòl</small>Mesaj sa a se yon èskokri. Pa voye okenn kòd.</div></div>
        </article>
        <article class="tile reveal">
          <div class="tile-ico"><?= icon('browser') ?></div>
          <h3>Chrome extension</h3>
          <p>A badge on every page from offline rules. Gemini Nano when you ask.</p>
          <div class="tile-visual">
            <div class="mini-browser">
              <div class="mb-bar"><span class="mb-url">westernunion-release.help</span><span class="mb-badge"><?= logo_svg('logo logo-sm') ?></span></div>
              <div class="mb-body"><span class="mb-line"></span><span class="mb-warn">Look-alike of Western Union</span></div>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- ============================================================ PERSONAS -->
  <section class="section">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="kicker">Who it protects</span>
        <h2>For the people <span class="serif">scammers target first.</span></h2>
      </div>
      <div class="personas">
        <article class="persona reveal"><p class="quote">"Your transfer is on hold. Pay the release fee."</p><h3>Families sending money home</h3><p>Fake transfer holds aimed at money sent to Haiti and Latin America.</p></article>
        <article class="persona reveal"><p class="quote">"Grandma, it's me. I need bail. Don't tell mom."</p><h3>Parents and grandparents</h3><p>Fake family emergencies that ask for gift cards.</p></article>
        <article class="persona reveal"><p class="quote">"Confirm your account or it will be closed."</p><h3>New to online banking</h3><p>Copies of real bank pages that are hard to tell apart.</p></article>
        <article class="persona reveal"><p class="quote">"Like videos, earn $300 a day. Small fee to start."</p><h3>Job seekers</h3><p>"Jobs" that start with a fee paid in crypto.</p></article>
      </div>
    </div>
  </section>

  <!-- ============================================================ STATS -->
  <section class="stats-band">
    <div class="wrap">
      <div class="section-head left reveal">
        <span class="kicker">Live numbers</span>
        <h2>Privacy you can measure.</h2>
        <p class="section-lead">Counted from real checks on this site. Veye logs only the verdict and where the AI ran.</p>
      </div>
      <div class="stats-grid">
        <div class="stat-big reveal"><b><?= number_format($total) ?></b><span>checks run</span></div>
        <div class="stat-big reveal"><b><?= $keptPct !== null ? $keptPct . '<em>%</em>' : '—' ?></b><span>of checks kept the original text on the device</span></div>
        <div class="stat-big reveal"><b><?= number_format((int) ($stats['scams'] ?? 0)) ?></b><span>scams caught</span></div>
        <div class="stat-big reveal"><b><?= number_format((int) ($stats['redacted'] ?? 0)) ?></b><span>personal items removed before the cloud</span></div>
      </div>
    </div>
  </section>

  <!-- ============================================================ JUDGES -->
  <section class="section" id="judges">
    <div class="wrap quick">
      <div class="reveal">
        <span class="kicker">For judges</span>
        <h2>Try it in <span class="serif">two minutes.</span></h2>
        <ol class="steps">
          <li><div><b>Turn on Gemini Nano (optional)</b><span>Chrome 139+ on desktop: enable <code>chrome://flags/#prompt-api-for-gemini-nano-multimodal-input</code> and restart Chrome.</span></div></li>
          <li><div><b>Sign in with the demo account</b><span>The username and password are on the right. No sign-up needed.</span></div></li>
          <li><div><b>Pick a sample and press Check</b><span>See <span class="ran-on ran-device inline-badge">On device</span> or <span class="ran-on ran-cloud inline-badge">Cloud · redacted</span> and open "How Veye decided".</span></div></li>
          <li><div><b>Install the extension</b><span>Check any page you visit, or right-click selected text.</span></div></li>
        </ol>
      </div>
      <div class="cred-card reveal">
        <h3><?= icon('sparkle', 'ico-sm') ?> Demo account</h3>
        <p class="muted">Shared with judges. Up to 300 checks a day.</p>
        <?= copy_row('Username', 'demo') ?>
        <?= copy_row('Password', 'VeyeDemo509') ?>
        <a class="btn btn-primary btn-block btn-lg mt" href="<?= e($checkUrl) ?>">Open the checker <?= icon('arrow') ?></a>
        <a class="btn btn-ghost btn-block mt" href="https://github.com/bastien509/veye" rel="noopener"><?= icon('github') ?>Read the code</a>
      </div>
    </div>
  </section>

  <!-- ============================================================ CTA -->
  <section class="cta-final">
    <div class="wrap">
      <div class="cta-box reveal">
        <?= logo_svg('logo logo-xl') ?>
        <h2>Before you answer, <span class="serif">veye.</span></h2>
        <p>Watch out first. Paste the message, get a calm answer, and keep your private life private.</p>
        <div class="cta-row">
          <a class="btn btn-primary btn-lg" href="<?= e($checkUrl) ?>"><?= icon('shield') ?>Check a message</a>
          <a class="btn btn-ghost btn-lg" href="<?= e(url('extension.php')) ?>"><?= icon('download') ?>Install the extension</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php
page_foot();
