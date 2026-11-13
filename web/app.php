<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$u = require_user();
$used = (int) q('SELECT COUNT(*) FROM veye_scans WHERE user_id = ? AND created_at >= UTC_DATE()', [$u['id']])->fetchColumn();
$mine = q(
    "SELECT COUNT(*) n, SUM(verdict = 'scam') scams, SUM(source = 'on_device') dev, SUM(redacted_items) red
       FROM veye_scans WHERE user_id = ? AND created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY",
    [$u['id']]
)->fetch() ?: [];
$limit = max(1, (int) $u['daily_limit']);
$usedPct = min(100, (int) round(100 * $used / $limit));

page_head('Check a message', 'page-app');
app_header($u, 'check');
?>
<main class="wrap" id="veyeApp" data-base="<?= e(url('')) ?>" data-csrf="<?= e(csrf_token()) ?>">
  <div class="dash-head">
    <div>
      <div class="crumbs"><?= icon('home', 'ico-sm') ?> Veye <span>/</span> Check</div>
      <h1>Is this a <span class="serif">scam?</span></h1>
      <p>Paste it. Veye asks your device first and tells you exactly where the AI ran.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('extension.php')) ?>"><?= icon('browser') ?>Check web pages with the extension</a>
  </div>

  <section class="kpis" aria-label="Your status">
    <div class="kpi k-ok" id="kpiDevice">
      <div class="kpi-ico"><?= icon('cpu') ?></div>
      <div><span>On-device AI</span><b id="kpiDeviceTxt">Checking…</b><small id="kpiDeviceSub">Gemini Nano in this browser</small></div>
    </div>
    <div class="kpi k-brand">
      <div class="kpi-ico"><?= icon('chart') ?></div>
      <div><span>Checks today</span><b><span id="usageN"><?= $used ?></span> <small>/ <?= $limit ?></small></b>
        <div class="kpi-bar"><i id="usageBar"></i></div></div>
    </div>
    <div class="kpi k-red">
      <div class="kpi-ico"><?= icon('alert') ?></div>
      <div><span>Scams caught · 30 days</span><b id="kpiScams"><?= (int) ($mine['scams'] ?? 0) ?></b><small><?= (int) ($mine['n'] ?? 0) ?> checks in total</small></div>
    </div>
    <div class="kpi k-gold">
      <div class="kpi-ico"><?= icon('lock') ?></div>
      <div><span>Kept private</span><b id="kpiRedacted"><?= (int) ($mine['red'] ?? 0) ?> item<?= (int) ($mine['red'] ?? 0) === 1 ? '' : 's' ?></b><small>removed before any cloud call</small></div>
    </div>
  </section>

  <div class="app-grid">
    <!-- ======================================================== COMPOSER -->
    <section class="card check-card" aria-labelledby="checkTitle">
      <div class="card-head">
        <h2 class="panel-title" id="checkTitle"><?= icon('message') ?>New check</h2>
        <span class="usage" id="usage" hidden><?= $used ?> / <?= $limit ?> today</span>
      </div>

      <div class="device-line" id="deviceLine" role="status">
        <span class="dot dot-wait"></span><span id="deviceText">Checking for Gemini Nano on this device…</span>
      </div>

      <div class="step">
        <div class="step-h">
          <span class="step-n">1</span><h2>What did you get?</h2>
          <div class="seg" role="tablist" aria-label="Input type">
            <button type="button" class="is-on" role="tab" aria-selected="true" data-tab="text"><?= icon('message') ?>Text</button>
            <button type="button" role="tab" aria-selected="false" data-tab="shot"><?= icon('image') ?>Screenshot</button>
          </div>
        </div>
        <div class="samples" id="samples" aria-label="Sample messages">
          <button type="button" class="chip-btn" data-sample="remit"><em>EN</em>Transfer "on hold"</button>
          <button type="button" class="chip-btn" data-sample="grandson"><em>EN</em>Grandson in jail</button>
          <button type="button" class="chip-btn" data-sample="job"><em>EN</em>Job with a fee</button>
          <button type="button" class="chip-btn" data-sample="kreyol"><em>HT</em>Kreyòl lottery</button>
          <button type="button" class="chip-btn" data-sample="safe"><em>EN</em>Real bank notice</button>
        </div>
        <div id="paneText">
          <div class="compose">
            <label class="sr-only" for="msg">Message or page text</label>
            <textarea id="msg" rows="7" maxlength="20000" placeholder="Paste the text message, email, WhatsApp chat or page text here…"></textarea>
            <div class="compose-foot"><button type="button" class="link-btn" id="clearBtn" hidden>Clear</button><span class="counter"><span id="msgCount">0</span> / 20000</span></div>
          </div>
        </div>
        <div id="paneShot" hidden>
          <label class="drop" id="drop">
            <input type="file" id="shot" accept="image/png,image/jpeg" hidden>
            <?= icon('upload') ?>
            <span class="drop-txt" id="dropTxt"><strong>Drop a screenshot</strong> or click to choose<br><small>PNG or JPEG, up to 6 MB. It is read on your device when Gemini Nano is ready.</small></span>
            <img id="shotPreview" alt="" hidden>
            <button type="button" class="link-btn" id="shotClear" hidden>Remove screenshot</button>
          </label>
        </div>
        <label class="field">
          <span>Link or sender <span class="muted">(optional)</span></span>
          <input id="src" maxlength="300" placeholder="https://… or +1 305 …" autocomplete="off">
        </label>
      </div>

      <div class="step">
        <div class="step-h"><span class="step-n">2</span><h2>Where may Veye think?</h2></div>
        <div class="choices" role="radiogroup" aria-label="Privacy mode">
          <label class="choice">
            <input type="radio" name="mode" value="private">
            <span class="choice-b"><?= icon('lock') ?><strong>Private</strong><small>This device only. Nothing leaves it, even the original text.</small><code>ONLY_ON_DEVICE</code></span>
          </label>
          <label class="choice">
            <input type="radio" name="mode" value="smart" checked>
            <span class="choice-b"><?= icon('cpu') ?><strong>Smart</strong><small>Device first. The cloud fallback gets redacted text only.</small><code>PREFER_ON_DEVICE</code></span>
          </label>
          <label class="choice">
            <input type="radio" name="mode" value="deep">
            <span class="choice-b"><?= icon('cloud') ?><strong>Deep check</strong><small>Cloud Gemini first, for long pages and screenshots.</small><code>PREFER_IN_CLOUD</code></span>
          </label>
        </div>
      </div>

      <div class="go-row">
        <label class="lang-select" title="Answer language"><?= icon('lang') ?><span class="sr-only">Answer in</span>
          <select id="lang">
            <option value="en">English</option>
            <option value="es">Español</option>
            <option value="fr">Français</option>
            <option value="ht">Kreyòl</option>
          </select>
        </label>
        <button type="button" class="btn btn-go btn-block" id="checkBtn"><span class="spin" aria-hidden="true"></span><?= icon('shield') ?><span id="checkLabel">Check it</span></button>
      </div>
      <p class="privacy-line"><?= icon('lock') ?>Veye keeps only the verdict and where the AI ran. Your text and screenshots are never stored.</p>
    </section>

    <!-- ======================================================== RESULT -->
    <section class="card result-card" id="result" aria-live="polite">
      <div class="empty" id="resultEmpty">
        <div class="empty-art"><?= logo_svg('logo logo-xl') ?></div>
        <h3>Your answer shows here</h3>
        <p>A verdict, the red flags, what to do next, and exactly where the AI ran.</p>
        <div class="empty-legend">
          <span class="ran-on ran-device">On device</span>
          <span class="ran-on ran-cloud">Cloud · redacted</span>
          <span class="ran-on ran-rules">Offline rules</span>
        </div>
      </div>
      <div id="resultBody" hidden>
        <div class="res-hero">
          <div class="ring ring-lg" id="ring"><b><span id="ringN">0</span><small>%</small></b></div>
          <div>
            <div class="res-top">
              <span class="verdict-chip" id="verdict"><span class="dot"></span><span id="verdictTxt"></span></span>
            </div>
            <p class="res-type" id="resType"></p>
            <span class="ran-on" id="ranOn"></span>
          </div>
        </div>
        <progress class="meter" id="meterBar" max="100" value="0" hidden></progress>
        <div class="metrics">
          <div class="metric" id="mWhere"><span>Ran on</span><b id="mWhereV">—</b></div>
          <div class="metric" id="mBytes"><span>Content sent</span><b id="mBytesV">—</b></div>
          <div class="metric"><span>Time</span><b id="mTimeV">—</b></div>
        </div>
        <p class="res-sum" id="resSummary"></p>
        <h3 class="res-h"><?= icon('alert', 'ico-sm') ?>Red flags</h3>
        <ul class="flags" id="resFlags"></ul>
        <h3 class="res-h"><?= icon('check', 'ico-sm') ?>What to do</h3>
        <ol class="todo" id="resTodo"></ol>
        <details class="trace" open>
          <summary><?= icon('route', 'ico-sm') ?>How Veye decided where to run</summary>
          <ol id="trace"></ol>
        </details>
        <div class="res-actions">
          <button type="button" class="btn btn-ghost" id="reportBtn"><?= icon('users') ?>Warn the community</button>
          <button type="button" class="btn btn-ghost" id="copyBtn"><?= icon('copy') ?>Copy advice</button>
        </div>
      </div>
      <div class="history" id="history" hidden>
        <h3 class="res-h"><?= icon('clock', 'ico-sm') ?>This session <span class="muted">(kept in this tab only)</span></h3>
        <ul id="historyList"></ul>
      </div>
    </section>
  </div>
</main>
<?php
page_foot(['veye-app.js']);
