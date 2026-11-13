// Result rendering shared by the web app and the extension side panel.
// Builds DOM nodes with textContent only (AI output is never parsed as HTML).

export const VERDICT_TEXT = { safe: 'Looks safe', suspicious: 'Suspicious', scam: 'Scam' };

export const TYPE_TEXT = {
  none: 'No scam pattern', phishing: 'Phishing (fake login or payment page)', fake_remittance: 'Fake money-transfer notice',
  impersonation: 'Someone pretending to be a company or official', family_emergency: 'Fake family emergency',
  lottery_prize: 'Fake prize or lottery', job_fee: 'Job offer that asks you to pay', romance: 'Romance scam',
  investment_crypto: 'Crypto or investment scam', tech_support: 'Fake tech support', delivery_fee: 'Fake delivery or customs fee',
  marketplace: 'Online marketplace scam', other: 'Other scam pattern',
};

export function ranOnText(r) {
  const secs = r.ms >= 1000 ? `${(r.ms / 1000).toFixed(1)} s` : `${r.ms} ms`;
  if (r.source === 'on_device') return `On this device · 0 bytes sent · ${secs}`;
  if (r.source === 'in_cloud') return `Cloud · ${r.redactedCount > 0 ? `${r.redactedCount} item(s) redacted` : 'redacted text'} · ${secs}`;
  return `Offline rules only · ${secs}`;
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

/** els: {empty, body, verdict, verdictTxt, ranOn, meterBar, type, summary, flags, todo, trace} */
export function renderResult(els, r) {
  els.empty.hidden = true;
  els.body.hidden = false;
  els.verdict.className = `verdict-chip v-${r.verdict}`;
  els.verdictTxt.textContent = `${VERDICT_TEXT[r.verdict]} · ${r.risk}% risk`;
  els.ranOn.className = `ran-on ran-${r.source === 'on_device' ? 'device' : r.source === 'in_cloud' ? 'cloud' : 'rules'}`;
  els.ranOn.textContent = ranOnText(r);
  els.meterBar.className = `meter m-${r.verdict}`;
  els.meterBar.value = r.risk;
  els.type.textContent = TYPE_TEXT[r.scamType] || TYPE_TEXT.other;
  els.summary.textContent = r.summary;
  fillList(els.flags, r.redFlags.length || r.ruleFlags.length ? [...r.redFlags] : ['No clear warning signs found.']);
  for (const f of r.ruleFlags) {
    const li = document.createElement('li');
    li.className = 'from-rules';
    li.textContent = f;
    li.title = 'Found by Veye\'s offline rules';
    els.flags.appendChild(li);
  }
  fillList(els.todo, r.nextSteps.length ? r.nextSteps : ['If you are unsure, contact the company with a number you already trust.']);
  fillList(els.trace, r.trace);
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
