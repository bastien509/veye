// Content script: scores the page with local rules only (no AI, no network)
// and hands the page text to Veye's own side panel when the user asks.
import { scoreRules } from './rules.js';

const MAX_TEXT = 20000;

function pageText() {
  const t = (document.body?.innerText || '').replace(/\n{3,}/g, '\n\n');
  return t.slice(0, MAX_TEXT);
}

function formRisks() {
  const flags = [];
  if (document.querySelector('input[type=password]')) flags.push('This page asks for a password');
  const cardish = [...document.querySelectorAll('input')].some((i) => /card|cc-?num|cvv|cvc|expir/i.test(`${i.name} ${i.id} ${i.autocomplete} ${i.placeholder}`));
  if (cardish) flags.push('This page asks for card details');
  return flags;
}

function scan() {
  const r = scoreRules(`${document.title}\n${pageText()}`, location.href);
  const forms = formRisks();
  // A password or card form only matters when the rules already see a look-alike or scam signs.
  let score = r.score;
  if (forms.length && r.hits.some((h) => ['lookalike', 'puny', 'ip', 'riskydomain'].includes(h.id))) score = Math.min(100, score + 30);
  const verdict = score >= 55 ? 'scam' : score >= 25 ? 'suspicious' : 'safe';
  chrome.runtime.sendMessage({ type: 'veye-rules', verdict, score, flags: [...r.hits.map((h) => h.flag), ...forms] }).catch(() => {});
}

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
  if (msg?.type === 'veye-extract') {
    const sel = String(window.getSelection() || '').trim();
    sendResponse({ text: sel || pageText(), selection: !!sel, url: location.href, title: document.title, forms: formRisks() });
  }
  return false;
});

if (document.readyState === 'complete') setTimeout(scan, 300);
else window.addEventListener('load', () => setTimeout(scan, 300), { once: true });
