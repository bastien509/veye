<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$u = require_user();
$used = (int) q('SELECT COUNT(*) FROM veye_scans WHERE user_id = ? AND created_at >= UTC_DATE()', [$u['id']])->fetchColumn();

page_head('Check a message', 'page-app');
app_header($u, 'check');
?>
<main class="wrap app-grid" id="veyeApp" data-base="<?= e(url('')) ?>" data-csrf="<?= e(csrf_token()) ?>">
  <section class="card check-card" aria-labelledby="checkTitle">
    <div class="card-head">
      <h1 id="checkTitle">Is this a scam?</h1>
      <span class="usage" id="usage"><?= $used ?> / <?= (int) $u['daily_limit'] ?> today</span>
    </div>

    <div class="device-line" id="deviceLine" role="status">
      <span class="dot dot-wait"></span><span id="deviceText">Checking for Gemini Nano on this device…</span>
    </div>

    <div class="step">
      <div class="step-h"><span class="step-n">1</span><h2>What did you get?</h2></div>
      <div class="samples" id="samples" aria-label="Sample messages">
        <button type="button" class="chip-btn" data-sample="remit">Remittance "on hold"</button>
        <button type="button" class="chip-btn" data-sample="grandson">Grandson in jail</button>
        <button type="button" class="chip-btn" data-sample="job">Job with a fee</button>
        <button type="button" class="chip-btn" data-sample="kreyol">Kreyòl lottery</button>
        <button type="button" class="chip-btn" data-sample="safe">Real bank notice</button>
      </div>
      <label class="field">
        <span class="sr-only">Message or page text</span>
        <textarea id="msg" rows="7" maxlength="20000" placeholder="Paste the text message, email, WhatsApp chat or page text here."></textarea>
      </label>
      <div class="counter"><span id="msgCount">0</span> / 20000</div>
      <label class="field">
        <span>Link or sender (optional)</span>
        <input id="src" maxlength="300" placeholder="https://… or +1 305 …" autocomplete="off">
      </label>
      <label class="drop" id="drop">
        <input type="file" id="shot" accept="image/png,image/jpeg" hidden>
        <span class="drop-txt" id="dropTxt"><strong>Add a screenshot</strong> (optional, PNG or JPEG)</span>
        <img id="shotPreview" alt="" hidden>
        <button type="button" class="link-btn" id="shotClear" hidden>Remove</button>
      </label>
    </div>

    <div class="step">
      <div class="step-h"><span class="step-n">2</span><h2>Where may Veye think?</h2></div>
      <div class="choices" role="radiogroup" aria-label="Privacy mode">
        <label class="choice">
          <input type="radio" name="mode" value="private">
          <span class="choice-b"><strong>Private</strong><small>This device only. Nothing leaves it, even the original text.</small></span>
        </label>
        <label class="choice">
          <input type="radio" name="mode" value="smart" checked>
          <span class="choice-b"><strong>Smart</strong><small>Device first. Cloud fallback gets redacted text only.</small></span>
        </label>
        <label class="choice">
          <input type="radio" name="mode" value="deep">
          <span class="choice-b"><strong>Deep check</strong><small>Cloud Gemini first, for long pages and screenshots.</small></span>
        </label>
      </div>
      <label class="field field-inline">
        <span>Answer in</span>
        <select id="lang">
          <option value="en">English</option>
          <option value="es">Español</option>
          <option value="fr">Français</option>
          <option value="ht">Kreyòl ayisyen</option>
        </select>
      </label>
    </div>

    <button type="button" class="btn btn-go btn-block" id="checkBtn">Check it</button>
    <p class="privacy-line">Veye keeps only the verdict and where the AI ran. Your text and screenshots are never stored.</p>
  </section>

  <section class="card result-card" id="result" aria-live="polite">
    <div class="empty" id="resultEmpty">
      <?= logo_svg('logo logo-xl') ?>
      <p>Your answer shows here: a verdict, the red flags, what to do next, and where the AI ran.</p>
    </div>
    <div id="resultBody" hidden>
      <div class="res-top">
        <span class="verdict-chip" id="verdict"><span class="dot"></span><span id="verdictTxt"></span></span>
        <span class="ran-on" id="ranOn"></span>
      </div>
      <progress class="meter" id="meterBar" max="100" value="0" aria-label="Risk"></progress>
      <p class="res-type" id="resType"></p>
      <p class="res-sum" id="resSummary"></p>
      <h3>Red flags</h3>
      <ul class="flags" id="resFlags"></ul>
      <h3>What to do</h3>
      <ol class="todo" id="resTodo"></ol>
      <details class="trace">
        <summary>How Veye decided where to run</summary>
        <ol id="trace"></ol>
      </details>
      <div class="res-actions">
        <button type="button" class="btn btn-ghost" id="reportBtn">Warn the community</button>
        <button type="button" class="btn btn-ghost" id="copyBtn">Copy advice</button>
      </div>
    </div>
  </section>
</main>
<?php
page_foot(['veye-app.js']);
