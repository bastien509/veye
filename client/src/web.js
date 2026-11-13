// Web app dashboard (petiht.com/veye/app.php).
import { createEngine, fileToImage } from './engine.js';
import { redact } from './redact.js';
import { renderResult, adviceText, DEVICE_TEXT, DEVICE_KPI, VERDICT_TEXT, fmtMs } from './ui.js';
import { SAMPLES } from './samples.js';

const root = document.getElementById('veyeApp');
const BASE = root.dataset.base.replace(/\/$/, '');
const CSRF = root.dataset.csrf;
const $ = (id) => document.getElementById(id);

async function api(path, body = {}) {
  const res = await fetch(`${BASE}/${path}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
    body: JSON.stringify(body),
  });
  const j = await res.json().catch(() => ({}));
  if (!res.ok) throw Object.assign(new Error(j.message || j.error || `HTTP ${res.status}`), { code: j.error, status: res.status });
  return j;
}

const els = {
  empty: $('resultEmpty'), body: $('resultBody'), verdict: $('verdict'), verdictTxt: $('verdictTxt'), ranOn: $('ranOn'),
  meterBar: $('meterBar'), type: $('resType'), summary: $('resSummary'), flags: $('resFlags'), todo: $('resTodo'), trace: $('trace'),
  ring: $('ring'), ringN: $('ringN'), mWhereV: $('mWhereV'), mBytesV: $('mBytesV'), mTimeV: $('mTimeV'), mBytes: $('mBytes'),
};
const msg = $('msg');
const src = $('src');
const lang = $('lang');
const btn = $('checkBtn');
const history = [];
let image = null;
let last = null;
let lastText = '';
let engine = null;

/* ------------------------------------------------------------ status */
function setDevice(state, extra = '') {
  const [kind, text] = DEVICE_TEXT[state] || DEVICE_TEXT.unavailable;
  $('deviceLine').querySelector('.dot').className = `dot dot-${kind}`;
  $('deviceText').textContent = text + extra;
  const [k1, k2] = DEVICE_KPI[state] || DEVICE_KPI.unavailable;
  $('kpiDeviceTxt').textContent = k1 + extra;
  $('kpiDeviceSub').textContent = k2;
  $('kpiDevice').className = `kpi ${state === 'available' ? 'k-ok' : state === 'downloadable' || state === 'downloading' ? 'k-gold' : 'k-brand'}`;
}

async function refreshDevice() {
  if (!engine || !engine.ready()) return setDevice('off');
  setDevice(await engine.deviceStatus(lang.value, !!image));
}

function setUsage(used, limit) {
  $('usageN').textContent = used;
  $('usageBar').style.width = `${Math.min(100, Math.round((100 * used) / Math.max(1, limit)))}%`;
}

async function boot() {
  const limit = Number(($('usage').textContent.split('/')[1] || '300').trim().split(' ')[0]) || 300;
  setUsage(Number($('usageN').textContent) || 0, limit);
  let config = {};
  try {
    const r = await fetch(`${BASE}/api/config.php`, { credentials: 'same-origin' });
    config = (await r.json()).config || {};
  } catch { /* offline: rules only */ }
  const mode = document.querySelector(`input[name=mode][value="${config.defaultMode}"]`);
  if (mode) mode.checked = true;
  engine = createEngine({
    config,
    getAppCheckToken: async () => {
      const j = await api('api/appcheck.php');
      return { token: j.token, expireTimeMillis: j.expireTimeMillis };
    },
  });
  refreshDevice();
}

/* ------------------------------------------------------------ inputs */
function syncCount() {
  $('msgCount').textContent = msg.value.length;
  $('clearBtn').hidden = msg.value.length === 0;
}
msg.addEventListener('input', syncCount);
$('clearBtn').addEventListener('click', () => { msg.value = ''; src.value = ''; syncCount(); msg.focus(); });
lang.addEventListener('change', refreshDevice);

document.querySelectorAll('.seg [data-tab]').forEach((b) => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.seg [data-tab]').forEach((x) => {
      x.classList.toggle('is-on', x === b);
      x.setAttribute('aria-selected', x === b ? 'true' : 'false');
    });
    $('paneText').hidden = b.dataset.tab !== 'text';
    $('paneShot').hidden = b.dataset.tab !== 'shot';
  });
});

$('samples').addEventListener('click', (e) => {
  const b = e.target.closest('[data-sample]');
  if (!b) return;
  const s = SAMPLES[b.dataset.sample];
  document.querySelector('.seg [data-tab="text"]').click();
  msg.value = s.text;
  src.value = s.source;
  lang.value = s.lang || 'en';
  syncCount();
  refreshDevice();
});

const shot = $('shot');
const preview = $('shotPreview');
function clearShot() {
  image = null;
  shot.value = '';
  preview.hidden = true;
  $('shotClear').hidden = true;
  $('dropTxt').hidden = false;
}
shot.addEventListener('change', async () => {
  try {
    image = shot.files[0] ? await fileToImage(shot.files[0]) : null;
    if (preview.src) URL.revokeObjectURL(preview.src);
    preview.src = image ? URL.createObjectURL(shot.files[0]) : '';
    preview.hidden = !image;
    $('shotClear').hidden = !image;
    $('dropTxt').hidden = !!image;
  } catch (err) {
    clearShot();
    alert(err.message);
  }
  refreshDevice();
});
$('shotClear').addEventListener('click', (e) => { e.preventDefault(); clearShot(); refreshDevice(); });
const drop = $('drop');
['dragover', 'dragenter'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, () => drop.classList.remove('is-over')));
drop.addEventListener('drop', (e) => {
  e.preventDefault();
  if (e.dataTransfer.files[0]) {
    const dt = new DataTransfer();
    dt.items.add(e.dataTransfer.files[0]);
    shot.files = dt.files;
    shot.dispatchEvent(new Event('change'));
  }
});

/* ------------------------------------------------------------ history */
function pushHistory(r, text) {
  history.unshift({ r, text: (text || '(screenshot)').replace(/\s+/g, ' ').slice(0, 90) });
  history.length = Math.min(history.length, 6);
  const ul = $('historyList');
  ul.replaceChildren();
  for (const h of history) {
    const li = document.createElement('li');
    const chip = document.createElement('span');
    chip.className = `verdict-chip v-${h.r.verdict}`;
    chip.textContent = VERDICT_TEXT[h.r.verdict];
    const t = document.createElement('span');
    t.className = 'h-txt';
    t.textContent = h.text;
    const s = document.createElement('span');
    s.className = 'h-src';
    s.textContent = `${h.r.source === 'on_device' ? 'device' : h.r.source === 'in_cloud' ? 'cloud' : 'rules'} · ${fmtMs(h.r.ms)}`;
    li.append(chip, t, s);
    ul.appendChild(li);
  }
  $('history').hidden = false;
}

/* ------------------------------------------------------------- check */
function busy(on, label) {
  btn.disabled = on;
  btn.classList.toggle('is-busy', on);
  $('checkLabel').textContent = label || (on ? 'Checking…' : 'Check it');
}

btn.addEventListener('click', async () => {
  if (!engine) return;
  const mode = document.querySelector('input[name=mode]:checked').value;
  const useImage = !$('paneShot').hidden && image ? image : null;
  busy(true);
  try {
    // Starting the model download needs this click.
    const st = engine.ready() ? await engine.deviceStatus(lang.value, !!useImage) : 'off';
    if (st === 'downloadable' || st === 'downloading') {
      setDevice('downloading');
      busy(true, 'Downloading Gemini Nano…');
      await engine.prepareDevice(lang.value, (p) => setDevice('downloading', ` ${Math.round(p * 100)}%`));
      busy(true);
    }
    const r = await engine.analyze({
      text: msg.value, source: src.value, image: useImage, mode, lang: lang.value,
      confirmCloud: async (q) => window.confirm(q),
    });
    last = r;
    lastText = msg.value;
    renderResult(els, r);
    pushHistory(r, msg.value);
    $('reportBtn').disabled = r.verdict === 'safe';
    $('reportBtn').lastChild.textContent = 'Warn the community';
    if (window.matchMedia('(max-width: 920px)').matches) $('result').scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (r.verdict === 'scam') $('kpiScams').textContent = String(Number($('kpiScams').textContent) + 1);
    if (r.redactedCount) { const n = (parseInt($('kpiRedacted').textContent, 10) || 0) + r.redactedCount; $('kpiRedacted').textContent = `${n} item${n === 1 ? '' : 's'}`; }
    api('api/scan.php', {
      source: r.source, mode, verdict: r.verdict, risk: r.risk, scamType: r.scamType, lang: lang.value,
      ms: r.ms, hadImage: r.hadImage, redacted: r.redactedCount, client: 'web',
    }).then((j) => setUsage(j.used, j.limit)).catch((e) => {
      if (e.code === 'daily_limit') $('kpiDeviceSub').textContent = 'Daily limit reached';
    });
  } catch (err) {
    alert(err.message || String(err));
  } finally {
    busy(false);
    refreshDevice();
  }
});

$('reportBtn').addEventListener('click', async () => {
  if (!last) return;
  const b = $('reportBtn');
  b.disabled = true;
  try {
    const j = await api('api/report.php', {
      verdict: last.verdict, risk: last.risk, scamType: last.scamType, lang: last.lang,
      domain: last.hosts[0] || '', snippet: redact(lastText).text.slice(0, 400), redFlags: [...last.redFlags, ...last.ruleFlags].slice(0, 6),
    });
    b.lastChild.textContent = j.status === 'approved' ? 'Shared with the community' : 'Sent for review';
  } catch (e) {
    b.disabled = false;
    alert(e.message);
  }
});

$('copyBtn').addEventListener('click', async () => {
  if (!last) return;
  try {
    await navigator.clipboard.writeText(adviceText(last));
    $('copyBtn').lastChild.textContent = 'Copied';
    setTimeout(() => { $('copyBtn').lastChild.textContent = 'Copy advice'; }, 1500);
  } catch { /* clipboard blocked */ }
});

window.addEventListener('online', refreshDevice);
window.addEventListener('offline', refreshDevice);
boot();
