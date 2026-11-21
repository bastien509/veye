// The one prompt and output schema used both on device (Gemini Nano via the
// Prompt API) and in the cloud (Gemini via Firebase AI Logic).
import { Schema } from 'firebase/ai';

export const SCAM_TYPES = ['none', 'phishing', 'fake_remittance', 'impersonation', 'family_emergency', 'lottery_prize',
  'job_fee', 'romance', 'investment_crypto', 'tech_support', 'delivery_fee', 'marketplace', 'other'];

export const LANG_NAMES = { en: 'English', es: 'Spanish', fr: 'French', ht: 'Haitian Creole (Kreyòl ayisyen)' };

/** JSON Schema for Chrome's Prompt API responseConstraint (on device). */
export const DEVICE_SCHEMA = {
  type: 'object',
  properties: {
    verdict: { type: 'string', enum: ['safe', 'suspicious', 'scam'] },
    risk: { type: 'integer', minimum: 0, maximum: 100 },
    scam_type: { type: 'string', enum: SCAM_TYPES },
    summary: { type: 'string' },
    red_flags: { type: 'array', items: { type: 'string' }, maxItems: 5 },
    next_steps: { type: 'array', items: { type: 'string' }, maxItems: 4 },
  },
  required: ['verdict', 'risk', 'scam_type', 'summary', 'red_flags', 'next_steps'],
  additionalProperties: false,
};

/** Same shape for Gemini in the cloud (Firebase AI Logic Schema). */
export const CLOUD_SCHEMA = Schema.object({
  properties: {
    verdict: Schema.enumString({ enum: ['safe', 'suspicious', 'scam'] }),
    risk: Schema.integer(),
    scam_type: Schema.enumString({ enum: SCAM_TYPES }),
    summary: Schema.string(),
    red_flags: Schema.array({ items: Schema.string() }),
    next_steps: Schema.array({ items: Schema.string() }),
  },
});

export function buildPrompt({ text, source, lang, ruleHits, hasImage, redacted }) {
  const hints = ruleHits.length ? ruleHits.map((h) => `- ${h.flag}`).join('\n') : '- none';
  return [
    'You are Veye, a calm scam-safety helper for ordinary people, many of them older adults or new to the internet.',
    'Decide whether the content below is a scam, phishing or fraud attempt aimed at the reader.',
    'Rules:',
    `- Write summary, red_flags and next_steps in ${LANG_NAMES[lang] || 'English'}. Use short, plain sentences.`,
    '- summary: at most 45 words. Say clearly whether it is safe, suspicious or a scam, and why.',
    '- red_flags: up to 5 concrete things in THIS content that are warning signs. Empty if none.',
    '- next_steps: up to 4 practical actions (for example: do not reply, call the bank on the number on your card, block the sender).',
    '- risk: 0 (clearly safe) to 100 (certain scam).',
    '- Real banks, transfer services and governments never ask for one-time codes, gift cards or crypto.',
    '- Never ask the reader for personal data.',
    redacted ? '- Personal data was replaced with tags like [PHONE], [CODE], [CARD], [EMAIL]. That is normal; do not treat the tags as red flags.' : '',
    hasImage ? '- A screenshot is attached. Read the text in it.' : '',
    '',
    'Warning signs a local rule check already found (may be incomplete or wrong):',
    hints,
    '',
    `Where the reader saw it: ${source ? source.slice(0, 300) : 'unknown'}`,
    'Content:',
    '"""',
    text || '(see screenshot)',
    '"""',
  ].filter((l) => l !== '').join('\n');
}

/** Parses the model's JSON, tolerating code fences or extra prose. */
export function parseAnswer(raw) {
  let s = String(raw || '').trim();
  s = s.replace(/^```(?:json)?\s*/i, '').replace(/```\s*$/, '');
  const a = s.indexOf('{');
  const b = s.lastIndexOf('}');
  if (a < 0 || b <= a) throw new Error('The AI answer was not JSON.');
  const j = JSON.parse(s.slice(a, b + 1));
  const verdict = ['safe', 'suspicious', 'scam'].includes(j.verdict) ? j.verdict : 'suspicious';
  return {
    verdict,
    risk: Math.max(0, Math.min(100, Math.round(Number(j.risk) || 0))),
    scamType: SCAM_TYPES.includes(j.scam_type) ? j.scam_type : 'other',
    summary: String(j.summary || '').slice(0, 600),
    // A small model sometimes echoes a redaction tag ("[PHONE]") as a flag; a tag alone is never a warning sign.
    redFlags: (Array.isArray(j.red_flags) ? j.red_flags : []).map((f) => String(f).trim())
      .filter((f) => f && !/^\[?(PHONE|EMAIL|CODE|CARD|IBAN|SSN|ID|LINK|URL|NAME)\]?\.?$/i.test(f)).slice(0, 5),
    nextSteps: (Array.isArray(j.next_steps) ? j.next_steps : []).map(String).filter(Boolean).slice(0, 4),
  };
}
