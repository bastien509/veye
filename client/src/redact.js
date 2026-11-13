// Runs on the device before anything can reach the cloud. It removes what
// identifies the person or unlocks their money, and keeps what shows the scam
// (the wording, money amounts and link domains).

const PATTERNS = [
  ['[EMAIL]', /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi],
  ['URL', /\bhttps?:\/\/([a-z0-9.-]+)(?::\d+)?(?:[/?#][^\s<>"']*)?/gi],
  ['[IBAN]', /\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){3,7}\b/g],
  ['[CARD]', /\b(?:\d[ -]?){13,19}\b/g],
  ['[SSN]', /\b\d{3}-\d{2}-\d{4}\b/g],
  ['[PHONE]', /(?:\+|\b00)?\d{1,3}?[\s.-]?\(?\d{2,4}\)?[\s.-]?\d{3,4}[\s.-]?\d{3,4}\b/g],
  ['[CODE]', /\b\d{4,8}\b/g],
];

const MONEY_BEFORE = /(?:[$€£]|USD|HTG|US\$|G|gdes?)\s?$/i;

/** Returns { text, count }: the redacted text and how many items were removed. */
export function redact(input) {
  let text = String(input || '');
  let count = 0;
  for (const [label, re] of PATTERNS) {
    text = text.replace(re, (match, ...args) => {
      if (label === 'URL') {
        // Links keep only their domain: paths and query strings often carry tokens.
        const host = args[0];
        if (match.length > `https://${host}`.length + 1) count++;
        return `https://${host}/…`;
      }
      if (label === '[CODE]') {
        // Money amounts ($1500, 2000 HTG) show the scam and identify nobody.
        const offset = args[args.length - 2];
        const whole = args[args.length - 1];
        if (MONEY_BEFORE.test(whole.slice(Math.max(0, offset - 5), offset))) return match;
        if (/^\s?(usd|htg|dollars?|gourdes?|goud)\b/i.test(whole.slice(offset + match.length, offset + match.length + 9))) return match;
      }
      count++;
      return label;
    });
  }
  return { text, count };
}
