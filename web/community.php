<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$u = current_user();
$types = q(
    "SELECT scam_type, COUNT(*) AS n FROM veye_scans
      WHERE verdict <> 'safe' AND created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY
      GROUP BY scam_type ORDER BY n DESC LIMIT 8"
)->fetchAll();
$max = max(1, (int) ($types[0]['n'] ?? 1));
$reports = q(
    "SELECT scam_type, verdict, risk, domain, snippet, red_flags, lang, created_at
       FROM veye_reports WHERE status = 'approved' ORDER BY created_at DESC LIMIT 40"
)->fetchAll();

function type_label(string $t): string
{
    return ucfirst(str_replace('_', ' ', $t));
}

page_head('Community warnings', 'page-community');
app_header($u, 'community');
?>
<main class="wrap">
  <div class="page-hero">
    <span class="kicker">Community</span>
    <h1>Scams going around <span class="serif">right now.</span></h1>
    <p class="section-lead">What people checked with Veye in the last 30 days. Shared warnings keep only redacted text and the site's domain, and are reviewed before they appear here.</p>
  </div>
  <div class="grid2 grid-top">
    <section class="card">
      <h2 class="panel-title"><?= icon('chart') ?>Most common this month</h2>
      <p></p>
      <?php if (!$types): ?>
        <p class="muted">No checks yet this month.</p>
      <?php else: ?>
        <ul class="bars">
          <?php foreach ($types as $t): ?>
            <li>
              <span class="bar-l"><?= e(type_label($t['scam_type'])) ?></span>
              <progress class="bar" max="<?= $max ?>" value="<?= (int) $t['n'] ?>"></progress>
              <span class="bar-n"><?= (int) $t['n'] ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <section class="card">
      <h2 class="panel-title"><?= icon('shield') ?>Four rules that stop most scams</h2>
      <p></p>
      <ul class="ticks">
        <li>No real bank, transfer service or government asks for a one-time code by text.</li>
        <li>Deadlines like "within 30 minutes" are there to stop you from thinking.</li>
        <li>Gift cards and crypto are never a way to pay a fee, a fine or a release.</li>
        <li>Call the company on the number printed on your card, not the one in the message.</li>
      </ul>
    </section>
  </div>

  <section class="reports">
    <h2 class="panel-title"><?= icon('alert') ?>Latest warnings</h2>
    <?php if (!$reports): ?>
      <p class="muted">No approved warnings yet.</p>
    <?php endif; ?>
    <?php foreach ($reports as $r): ?>
      <article class="card report">
        <div class="report-top">
          <span class="verdict-chip v-<?= e($r['verdict']) ?>"><span class="dot"></span><?= e(ucfirst($r['verdict'])) ?> · <?= (int) $r['risk'] ?>%</span>
          <span class="tag"><?= e(type_label($r['scam_type'])) ?></span>
          <?php if ($r['domain'] !== ''): ?><span class="tag tag-domain"><?= e($r['domain']) ?></span><?php endif; ?>
          <time class="muted" datetime="<?= e($r['created_at']) ?>Z"><?= e(substr($r['created_at'], 0, 10)) ?></time>
        </div>
        <?php if ($r['snippet'] !== ''): ?><p class="snippet">“<?= e($r['snippet']) ?>”</p><?php endif; ?>
        <?php if ($r['red_flags'] !== ''): ?>
          <ul class="flags flags-sm">
            <?php foreach (explode("\n", $r['red_flags']) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
</main>
<?php
page_foot();
