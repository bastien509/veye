// Web app (petiht.com/veye/app.php).
import { createEngine, fileToImage } from './engine.js';
import { redact } from './redact.js';
import { renderResult, adviceText, DEVICE_TEXT } from './ui.js';
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
};
const msg = $('msg');
const src = $('src');
const lang = $('lang');
const btn = $('checkBtn');
let image = null;
let last = null;
let lastText = '';
let engine = null;

function setDevice(state, extra = '') {
  const [kind, text] = DEVICE_TEXT[state] || DEVICE_TEXT.unavailable;
  $('deviceLine').querySelector('.dot').className = `dot dot-${kind}`;
  $('deviceText').textContent = text + extra;
}

async function refreshDevice() {
  if (!engine || !engine.ready()) return setDevice('off');
  setDevice(await engine.deviceStatus(lang.value, !!image));
}

async function boot() {
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
msg.addEventListener('input', () => { $('msgCount').textContent = msg.value.length; });
lang.addEventListener('change', refreshDevice);
$('samples').addEventListener('click', (e) => {
  const b = e.target.closest('[data-sample]');
  if (!b) return;
  const s = SAMPLES[b.dataset.sample];
  msg.value = s.text;
  src.value = s.source;
  if (s.lang) lang.value = s.lang;
  msg.dispatchEvent(new Event('input'));
  refreshDevice();
});

const shot = $('shot');
const preview = $('shotPreview');
shot.addEventListener('change', async () => {
  try {
    image = shot.files[0] ? await fileToImage(shot.files[0]) : null;
    if (preview.src) URL.revokeObjectURL(preview.src);
    preview.src = image ? URL.createObjectURL(shot.files[0]) : '';
    preview.hidden = !image;
    $('shotClear').hidden = !image;
    $('dropTxt').hidden = !!image;
  } catch (err) {
    image = null;
    shot.value = '';
    alert(err.message);
  }
  refreshDevice();
});
$('shotClear').addEventListener('click', (e) => {
  e.preventDefault();
  image = null;
  shot.value = '';
  preview.hidden = true;
  $('shotClear').hidden = true;
  $('dropTxt').hidden = false;
  refreshDevice();
});
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

/* ------------------------------------------------------------- check */
btn.addEventListener('click', async () => {
  if (!engine) return;
  const mode = document.querySelector('input[name=mode]:checked').value;
  btn.disabled = true;
  btn.textContent = 'Checking…';
  try {
    // Starting the model download needs this click.
    const st = engine.ready() ? await engine.deviceStatus(lang.value, !!image) : 'off';
    if (st === 'downloadable' || st === 'downloading') {
      setDevice('downloading');
      await engine.prepareDevice(lang.value, (p) => setDevice('downloading', ` ${Math.round(p * 100)}%`));
    }
    const r = await engine.analyze({
      text: msg.value, source: src.value, image, mode, lang: lang.value,
      confirmCloud: async (q) => window.confirm(q),
    });
    last = r;
    lastText = msg.value;
    renderResult(els, r);
    $('reportBtn').disabled = r.verdict === 'safe';
    $('reportBtn').textContent = 'Warn the community';
    els.body.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    api('api/scan.php', {
      source: r.source, mode, verdict: r.verdict, risk: r.risk, scamType: r.scamType, lang: lang.value,
      ms: r.ms, hadImage: r.hadImage, redacted: r.redactedCount, client: 'web',
    }).then((j) => { $('usage').textContent = `${j.used} / ${j.limit} today`; }).catch((e) => {
      if (e.code === 'daily_limit') $('usage').textContent = 'Daily limit reached';
    });
  } catch (err) {
    alert(err.message || String(err));
  } finally {
    btn.disabled = false;
    btn.textContent = 'Check it';
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
    b.textContent = j.status === 'approved' ? 'Shared with the community' : 'Sent for review';
  } catch (e) {
    b.disabled = false;
    alert(e.message);
  }
});

$('copyBtn').addEventListener('click', async () => {
  if (!last) return;
  try {
    await navigator.clipboard.writeText(adviceText(last));
    $('copyBtn').textContent = 'Copied';
    setTimeout(() => { $('copyBtn').textContent = 'Copy advice'; }, 1500);
  } catch { /* clipboard blocked */ }
});

window.addEventListener('online', refreshDevice);
window.addEventListener('offline', refreshDevice);
boot();
