// Result rendering shared by the web app and the extension side panel.
// AI output only ever goes into textContent; the icons are fixed strings.

export const VERDICT_TEXT = { safe: 'Looks safe', suspicious: 'Suspicious', scam: 'Scam' };

export const TYPE_TEXT = {
  none: 'No scam pattern', phishing: 'Phishing (fake login or payment page)', fake_remittance: 'Fake money-transfer notice',
  impersonation: 'Someone pretending to be a company or official', family_emergency: 'Fake family emergency',
  lottery_prize: 'Fake prize or lottery', job_fee: 'Job offer that asks you to pay', romance: 'Romance scam',
  investment_crypto: 'Crypto or investment scam', tech_support: 'Fake tech support', delivery_fee: 'Fake delivery or customs fee',
  marketplace: 'Online marketplace scam', other: 'Other scam pattern',
};

const ICONS = {
  rules: '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
  device: '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6" rx="1"/><path d="M15 2v2M15 20v2M2 15h2M2 9h2M20 15h2M20 9h2M9 2v2M9 20v2"/>',
  cloud: '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
  lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  warn: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
  route: '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
};

function traceKind(line) {
  const l = line.toLowerCase();
  if (l.startsWith('local rules') || l.startsWith('rule flags')) return 'rules';
  if (l.startsWith('redacted') || l.includes('kept the screenshot') || l.includes('stays on this device')) return 'lock';
  if (l.includes('failed') || l.includes('did not answer') || l.includes('but the rules')) return 'warn';
  if (l.startsWith('answered by gemini nano') || l.includes('only_on_device') || l.includes('gemini nano on this device')) return 'device';
  if (l.includes('cloud')) return 'cloud';
  return 'route';
}

export function fmtMs(ms) {
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)} s` : `${ms} ms`;
}

export function ranOnText(r) {
  if (r.source === 'on_device') return `On this device · 0 bytes sent · ${fmtMs(r.ms)}`;
  if (r.source === 'in_cloud') return `Cloud · ${r.redactedCount > 0 ? `${r.redactedCount} item(s) redacted` : 'redacted text'} · ${fmtMs(r.ms)}`;
  return `Offline rules only · ${fmtMs(r.ms)}`;
}

function fillList(el, items, cls) {
  el.replaceChildren();
  for (const t of items) {
    const li = document.createElement('li');
    li.textContent = t;
    if (cls) li.className = cls;
    el.appendChild(li);
  }
}

function svg(name) {
  return `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name] || ICONS.route}</svg>`;
}

/** Counts a number up for the risk ring. */
function animateRing(ring, num, target) {
  const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const start = performance.now();
  const dur = reduce ? 0 : 900;
  const step = (now) => {
    const t = dur ? Math.min(1, (now - start) / dur) : 1;
    const v = Math.round(target * (1 - (1 - t) ** 3));
    ring.style.setProperty('--risk', String(v));
    if (num) num.textContent = String(v);
    if (t < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

/**
 * els: {empty, body, verdict, verdictTxt, ranOn, meterBar, type, summary, flags, todo, trace}
 * optional: ring, ringN, mWhere, mWhereV, mBytesV, mTimeV, mBytes
 */
export function renderResult(els, r) {
  els.empty.hidden = true;
  els.body.hidden = false;
  els.verdict.className = `verdict-chip v-${r.verdict}`;
  els.verdictTxt.textContent = els.ring ? VERDICT_TEXT[r.verdict] : `${VERDICT_TEXT[r.verdict]} · ${r.risk}% risk`;
  els.ranOn.className = `ran-on ran-${r.source === 'on_device' ? 'device' : r.source === 'in_cloud' ? 'cloud' : 'rules'}`;
  els.ranOn.textContent = ranOnText(r);
  if (els.meterBar) {
    els.meterBar.className = `meter m-${r.verdict}`;
    els.meterBar.value = r.risk;
  }
  if (els.ring) {
    els.ring.className = `ring ring-lg t-${r.verdict}`;
    animateRing(els.ring, els.ringN, r.risk);
  }
  if (els.mWhereV) {
    els.mWhereV.textContent = r.source === 'on_device' ? 'This device' : r.source === 'in_cloud' ? 'Cloud' : 'Rules only';
    els.mBytesV.textContent = r.sentBytes > 0 ? `~${Math.max(1, Math.round(r.sentBytes / 1024))} KB, redacted` : '0 bytes';
    els.mTimeV.textContent = fmtMs(r.ms);
    els.mBytes.className = `metric${r.sentBytes > 0 ? '' : ' m-good'}`;
  }
  els.type.textContent = TYPE_TEXT[r.scamType] || TYPE_TEXT.other;
  els.summary.textContent = r.summary;
  if (r.redFlags.length || r.ruleFlags.length) {
    fillList(els.flags, r.redFlags);
    for (const f of r.ruleFlags) {
      const li = document.createElement('li');
      li.className = 'from-rules';
      li.textContent = f;
      li.title = 'Found by Veye\'s offline rules';
      els.flags.appendChild(li);
    }
  } else {
    fillList(els.flags, ['No clear warning signs found.'], 'none');
  }
  fillList(els.todo, r.nextSteps.length ? r.nextSteps : ['If you are unsure, contact the company with a number you already trust.']);
  els.trace.replaceChildren();
  for (const line of r.trace) {
    const li = document.createElement('li');
    const kind = traceKind(line);
    li.className = `t-${kind}`;
    const i = document.createElement('i');
    i.innerHTML = svg(kind);
    const s = document.createElement('span');
    s.textContent = line;
    li.append(i, s);
    els.trace.appendChild(li);
  }
}

export function adviceText(r) {
  return [
    `Veye: ${VERDICT_TEXT[r.verdict]} (${r.risk}% risk) — ${TYPE_TEXT[r.scamType] || ''}`,
    r.summary,
    '',
    'Red flags:',
    ...[...r.redFlags, ...r.ruleFlags].map((f) => `• ${f}`),
    '',
    'What to do:',
    ...r.nextSteps.map((s, i) => `${i + 1}. ${s}`),
  ].join('\n');
}

export const DEVICE_TEXT = {
  available: ['ok', 'Gemini Nano is ready on this device. Checks stay private.'],
  downloadable: ['wait', 'Gemini Nano can be downloaded to this device. The first check starts the download.'],
  downloading: ['wait', 'Gemini Nano is downloading to this device…'],
  unavailable: ['cloud', 'No Gemini Nano on this device. Veye will use the cloud with redacted text.'],
  unsupported: ['cloud', 'This browser has no built-in AI. Veye will use the cloud with redacted text. (Chrome 139+ with the Prompt API flag runs it locally.)'],
  off: ['off', 'AI keys are not set up on this server yet. Veye runs its offline rules only.'],
};

export const DEVICE_KPI = {
  available: ['Ready', 'Gemini Nano runs here'],
  downloadable: ['Downloadable', 'Starts on your first check'],
  downloading: ['Downloading…', 'Gemini Nano'],
  unavailable: ['Cloud fallback', 'No local model here'],
  unsupported: ['Cloud fallback', 'Use Chrome 139+ for on-device'],
  off: ['Rules only', 'AI not configured'],
};
