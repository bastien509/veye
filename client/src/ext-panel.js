// Chrome extension side panel. Same engine as the web app; the Prompt API
// (Gemini Nano) is available to extension pages, so page text is read on device.
import { createEngine } from './engine.js';
import { redact } from './redact.js';
import { renderResult, adviceText, DEVICE_TEXT } from './ui.js';

/* global __VEYE_BASE__ */
const BASE = typeof __VEYE_BASE__ === 'string' ? __VEYE_BASE__ : 'https://petiht.com/veye';
const $ = (id) => document.getElementById(id);

let token = '';
let engine = null;
let last = null;
let lastText = '';

async function api(path, body = {}) {
  const res = await fetch(`${BASE}/${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: JSON.stringify(body),
  });
  const j = await res.json().catch(() => ({}));
  if (res.status === 401 && path !== 'api/ext-login.php') signedOut();
  if (!res.ok) throw Object.assign(new Error(j.message || j.error || `HTTP ${res.status}`), { code: j.error });
  return j;
}

function signedOut() {
  token = '';
  chrome.storage.local.remove(['token', 'user']);
  $('checker').hidden = true;
  $('userBox').hidden = true;
  $('loginForm').hidden = false;
}

async function showChecker(user) {
  $('loginForm').hidden = true;
  $('checker').hidden = false;
  $('userBox').hidden = false;
  $('userName').textContent = user?.name || '';
  if (!engine) {
    let config = {};
    try {
      config = (await (await fetch(`${BASE}/api/config.php`)).json()).config || {};
    } catch { /* offline: rules only */ }
    const m = document.querySelector(`input[name=mode][value="${config.defaultMode}"]`);
    if (m) m.checked = true;
    engine = createEngine({
      config,
      getAppCheckToken: async () => {
        const j = await api('api/appcheck.php');
        return { token: j.token, expireTimeMillis: j.expireTimeMillis };
      },
    });
  }
  refreshDevice();
  loadPending();
  showPageRules();
}

function setDevice(state, extra = '') {
  const [kind, text] = DEVICE_TEXT[state] || DEVICE_TEXT.unavailable;
  $('devDot').className = `dot dot-${kind}`;
  $('deviceText').textContent = text + extra;
}

async function refreshDevice() {
  if (!engine || !engine.ready()) return setDevice('off');
  setDevice(await engine.deviceStatus($('lang').value));
}

async function activeTab() {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  return tab;
}

async function showPageRules() {
  const tab = await activeTab();
  const box = $('pageRules');
  if (!tab?.id) return;
  const data = (await chrome.storage.session.get(`rules-${tab.id}`))[`rules-${tab.id}`];
  if (data && data.verdict !== 'safe') {
    box.hidden = false;
    box.className = `page-rules${data.verdict === 'scam' ? ' is-scam' : ''}`;
    box.textContent = `This page: ${data.verdict} by offline rules — ${data.flags.slice(0, 3).join('; ')}`;
  } else {
    box.hidden = true;
  }
}

async function loadPending() {
  const { pending } = await chrome.storage.session.get('pending');
  if (pending && Date.now() - pending.at < 120000) {
    $('msg').value = pending.text;
    $('src').value = pending.source;
    await chrome.storage.session.remove('pending');
  }
}

chrome.storage.session.onChanged.addListener((changes) => {
  if (changes.pending?.newValue) loadPending();
});
chrome.tabs.onActivated.addListener(showPageRules);

$('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  $('loginErr').hidden = true;
  try {
    const j = await api('api/ext-login.php', { username: $('lu').value, password: $('lp').value });
    token = j.token;
    await chrome.storage.local.set({ token, user: j.user });
    $('lp').value = '';
    showChecker(j.user);
  } catch (err) {
    $('loginErr').textContent = err.code === 'bad_login' ? 'Wrong username or password.' : err.code === 'rate_limited' ? 'Too many tries. Wait 15 minutes.' : 'Could not reach Veye.';
    $('loginErr').hidden = false;
  }
});

$('signOut').addEventListener('click', async () => {
  try { await api('api/ext-logout.php'); } catch { /* already gone */ }
  signedOut();
});

$('grabBtn').addEventListener('click', async () => {
  const tab = await activeTab();
  if (!tab?.id) return;
  try {
    const page = await chrome.tabs.sendMessage(tab.id, { type: 'veye-extract' });
    $('msg').value = page.text;
    $('src').value = page.url;
  } catch {
    $('msg').value = '';
    $('src').value = tab.url || '';
    alert('Veye cannot read this page (Chrome pages and the Web Store are off limits). Reload the page and try again.');
  }
});

$('lang').addEventListener('change', refreshDevice);

$('checkBtn').addEventListener('click', async () => {
  const btn = $('checkBtn');
  const mode = document.querySelector('input[name=mode]:checked').value;
  const lang = $('lang').value;
  btn.disabled = true;
  btn.textContent = 'Checking…';
  try {
    const st = engine.ready() ? await engine.deviceStatus(lang) : 'off';
    if (st === 'downloadable' || st === 'downloading') {
      setDevice('downloading');
      await engine.prepareDevice(lang, (p) => setDevice('downloading', ` ${Math.round(p * 100)}%`));
    }
    const r = await engine.analyze({ text: $('msg').value, source: $('src').value, mode, lang, confirmCloud: async (q) => window.confirm(q) });
    last = r;
    lastText = $('msg').value;
    renderResult({
      empty: $('resultEmpty'), body: $('resultBody'), verdict: $('verdict'), verdictTxt: $('verdictTxt'), ranOn: $('ranOn'),
      meterBar: $('meterBar'), type: $('resType'), summary: $('resSummary'), flags: $('resFlags'), todo: $('resTodo'), trace: $('trace'),
    }, r);
    $('reportBtn').disabled = r.verdict === 'safe';
    $('reportBtn').textContent = 'Warn the community';
    api('api/scan.php', { source: r.source, mode, verdict: r.verdict, risk: r.risk, scamType: r.scamType, lang, ms: r.ms, hadImage: false, redacted: r.redactedCount }).catch(() => {});
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
  $('reportBtn').disabled = true;
  try {
    const j = await api('api/report.php', {
      verdict: last.verdict, risk: last.risk, scamType: last.scamType, lang: last.lang, domain: last.hosts[0] || '',
      snippet: redact(lastText).text.slice(0, 400), redFlags: [...last.redFlags, ...last.ruleFlags].slice(0, 6),
    });
    $('reportBtn').textContent = j.status === 'approved' ? 'Shared' : 'Sent for review';
  } catch {
    $('reportBtn').disabled = false;
  }
});

$('copyBtn').addEventListener('click', () => {
  if (last) navigator.clipboard.writeText(adviceText(last)).catch(() => {});
});

(async () => {
  const s = await chrome.storage.local.get(['token', 'user']);
  if (s.token) {
    token = s.token;
    showChecker(s.user);
  } else {
    signedOut();
  }
})();
