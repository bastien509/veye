// Layer 1: instant, offline red-flag rules. No AI, no network.
// Patterns cover English, Haitian Kreyòl, French and Spanish.

const RULES = [
  { id: 'otp', w: 30, flag: 'Asks for a one-time or verification code',
    re: /\b(one[- ]?time|verification|security|confirmation|6[- ]?digit|otp)\s*(pass)?(code|number|pin)\b|\bkòd (verifikasyon|sekirite)\b|\bc[oó]digo de (verificaci[oó]n|seguridad)\b|\bcode de (v[ée]rification|s[ée]curit[ée])\b/i },
  { id: 'urgency', w: 15, flag: 'Pressure to act fast',
    re: /\b(urgent|immediately|right away|within \d+\s*(min|mins|minutes|hours?|hrs?)|act now|final (notice|warning)|last chance|today only|will be (cancel+ed|closed|suspended|deleted))\b|\b(imedyatman|ijan|kounye a menm)\b|\b(urgente|inmediatamente|de inmediato)\b|\b(imm[ée]diatement|dernier avertissement)\b/i },
  { id: 'locked', w: 15, flag: 'Says your account or money is blocked',
    re: /\b(account|card|transfer|payment|remittance|package|parcel)\b.{0,40}\b(on hold|suspended|locked|blocked|frozen|restricted|held)\b|\b(bloke|kenbe)\b.{0,20}\b(kont|lajan|transfè)\b|\b(bloqu[ée]e?|suspendu)\b|\b(bloqueada?|suspendida?|retenid[oa])\b/i },
  { id: 'giftcard', w: 30, flag: 'Wants payment in gift cards',
    re: /\b(gift ?cards?|itunes cards?|apple cards?|google play (cards?|codes?)|steam cards?|amazon cards?|kat kado|tarjetas? de regalo|cartes? cadeaux?)\b/i },
  { id: 'crypto', w: 20, flag: 'Mentions crypto payment or a wallet address',
    re: /\b(bitcoin|btc|usdt|tether|crypto(currency)?|wallet address|binance|bitcoin atm)\b|\b(bc1|0x)[a-z0-9]{25,}\b/i },
  { id: 'fee', w: 25, flag: 'Asks you to pay a fee to receive money or a job',
    re: /\b(release|processing|clearance|customs|delivery|training|registration|activation|unlock|transfer) (fee|charge|payment)\b|\bpay (a|the) (small )?fee\b|\bfrais (de|d')\s*\w+|\bpeye\b.{0,20}(frè|fre|lajan).{0,30}(debloke|resevwa)|\b(tarifa|cargo) de (env[ií]o|liberaci[oó]n|activaci[oó]n)\b/i },
  { id: 'prize', w: 25, flag: 'Prize, lottery or inheritance you never entered',
    re: /\b(you (have )?won|winner|lottery|lotto|jackpot|sweepstakes|inheritance|unclaimed funds)\b|\b(ou genyen|ou te genyen|gwo lo|bòlèt|loto)\b|\b(ganaste|ganador|loter[ií]a|herencia)\b|\b(vous avez gagn[ée]|gagnant|loterie|h[ée]ritage)\b/i },
  { id: 'family', w: 30, flag: 'Family emergency asking for money',
    re: /\b(grandson|granddaughter|grandma|grandpa|son|daughter|nephew|niece|mom|dad)\b.{0,60}\b(jail|arrest(ed)?|accident|hospital|bail|lawyer)\b|\bbail (money|bond)\b|\bpitit\b.{0,40}\b(prizon|lopital|aksidan)\b|\b(nieto|hijo|hija)\b.{0,40}\b(c[aá]rcel|accidente|hospital|fianza)\b/i },
  { id: 'creds', w: 25, flag: 'Asks you to confirm a password, card or ID',
    re: /\b(confirm|verify|update|enter|provide|send)\b.{0,40}\b(password|passcode|pin|card (number|details)|cvv|account (number|details)|bank details|ssn|social security|login)\b|\b(modpas|nimewo kat|kat kredi|kat bank)\b|\b(contrase[nñ]a|n[uú]mero de tarjeta)\b|\b(mot de passe|num[ée]ro de carte)\b/i },
  { id: 'secret', w: 20, flag: 'Tells you to keep it secret',
    re: /\b(don'?t tell (anyone|your|my|mom|mum|dad|the bank)|do not tell (anyone|your)|keep (this|it) (secret|between us|private)|do not contact (the|your) bank)\b|\b(pa di pèsonn|pa di bank lan)\b|\b(no (se )?lo digas|no le digas)\b|\b(n'en parlez (à|a) personne)\b/i },
  { id: 'shortlink', w: 15, flag: 'Hides the real link behind a short link',
    re: /\b(bit\.ly|tinyurl\.com|t\.co|is\.gd|rb\.gy|cutt\.ly|shorturl\.at|ow\.ly|tiny\.cc|s\.id)\/\S+/i },
  { id: 'remote', w: 25, flag: 'Wants remote access to your device',
    re: /\b(anydesk|teamviewer|remote (access|desktop|support)|quick ?support|ultraviewer|rustdesk)\b/i },
  { id: 'job', w: 15, flag: 'Too-easy job or income offer',
    re: /\b(work from home|easy money|no experience (needed|required)|earn \$?\d+[\d,]*\s*(\/|per|a)\s*(day|hour|week)|like (videos|posts) (and|to) earn|task (job|commission))\b|\b(travay lakay|fè lajan fasil)\b|\b(dinero f[aá]cil|trabajo desde casa)\b/i },
  { id: 'channel', w: 10, flag: 'Wants to move you to WhatsApp or Telegram',
    re: /\b(contact|text|message|add) (me|us)?\s*(on|via) (whatsapp|telegram|signal)\b|\bwa\.me\/|\bt\.me\//i },
  { id: 'money', w: 8, flag: 'Involves sending money',
    re: /\b(wire transfer|western union|moneygram|zelle|cash ?app|venmo|moncash|natcash|remittance|remitly|transfè lajan)\b/i },
];

const BRANDS = ['paypal', 'apple', 'icloud', 'amazon', 'netflix', 'microsoft', 'google', 'facebook', 'instagram', 'whatsapp',
  'chase', 'wellsfargo', 'bankofamerica', 'bofa', 'citibank', 'westernunion', 'moneygram', 'zelle', 'cashapp', 'venmo',
  'moncash', 'digicel', 'natcom', 'unibank', 'sogebank', 'bnc', 'capitalbank', 'irs', 'usps', 'fedex', 'dhl', 'ups', 'uscis', 'remitly'];

const OFFICIAL = /(^|\.)(paypal\.com|apple\.com|icloud\.com|amazon\.[a-z.]+|netflix\.com|microsoft\.com|live\.com|google\.com|facebook\.com|instagram\.com|whatsapp\.com|chase\.com|wellsfargo\.com|bankofamerica\.com|citi\.com|westernunion\.com|moneygram\.com|zellepay\.com|cash\.app|venmo\.com|digicelgroup\.com|digicelhaiti\.com|natcom\.com\.ht|unibankhaiti\.com|sogebank\.com|bnconline\.com|capitalbankhaiti\.biz|irs\.gov|usps\.com|fedex\.com|dhl\.com|ups\.com|uscis\.gov|remitly\.com|moncashbutton\.digicelgroup\.com)$/i;

const RISKY_TLD = /\.(top|xyz|icu|shop|live|click|buzz|rest|monster|cyou|cfd|sbs|quest|bar|support|help|online|site|info|co)$/i;

// "We will never ask for a one-time code" is a safety notice, not a red flag.
const NEGATION = /\b(never|not|won't|don't|will not|no one|nobody|jamais|nunca|pa janm|ne .{0,12}pas)\b[^.!?\n]{0,60}$/i;
const NEGATABLE = new Set(['otp', 'creds', 'giftcard', 'remote', 'crypto']);

function matchesUnnegated(rule, text) {
  if (!NEGATABLE.has(rule.id)) return rule.re.test(text);
  const re = new RegExp(rule.re.source, rule.re.flags.includes('g') ? rule.re.flags : `${rule.re.flags}g`);
  let m;
  while ((m = re.exec(text)) !== null) {
    if (!NEGATION.test(text.slice(Math.max(0, m.index - 80), m.index))) return true;
    if (m[0] === '') re.lastIndex++;
  }
  return false;
}

/** All http(s) hosts mentioned in a text. */
export function hostsIn(text) {
  const hosts = new Set();
  const re = /\bhttps?:\/\/([a-z0-9.-]+\.[a-z]{2,})(?::\d+)?/gi;
  let m;
  while ((m = re.exec(text)) !== null) hosts.add(m[1].toLowerCase());
  const bare = /\b((?:[a-z0-9-]+\.)+(?:com|net|org|co|top|xyz|icu|shop|live|click|info|online|site|support|help|ht|io|me))\b(?![@.])/gi;
  while ((m = bare.exec(text)) !== null) hosts.add(m[1].toLowerCase());
  return [...hosts].slice(0, 20);
}

export function checkHost(host) {
  const flags = [];
  if (!host) return flags;
  const h = host.toLowerCase();
  const squashed = h.replace(/[-.]/g, '');
  if (/^\d+\.\d+\.\d+\.\d+$/.test(h)) flags.push({ id: 'ip', w: 25, flag: `Link goes to a bare IP address (${h})` });
  if (h.includes('xn--')) flags.push({ id: 'puny', w: 25, flag: `Link uses look-alike letters (${h})` });
  const brand = BRANDS.find((b) => squashed.includes(b));
  if (brand && !OFFICIAL.test(h)) flags.push({ id: 'lookalike', w: 30, flag: `"${h}" uses the name ${brand} but is not its real site` });
  else if (RISKY_TLD.test(h) && /(secure|verify|login|account|update|support|help|release|refund|claim|bonus|wallet)/.test(h)) {
    flags.push({ id: 'riskydomain', w: 20, flag: `Suspicious domain name (${h})` });
  }
  if (h.split('.').length > 4) flags.push({ id: 'deepsub', w: 10, flag: `Unusually long web address (${h})` });
  return flags;
}

/**
 * Scores text (and an optional page URL) with the local rules.
 * extraRules come from the admin panel / Remote Config: [{pattern, flag, weight}].
 */
export function scoreRules(text, pageUrl = '', extraRules = []) {
  const hits = [];
  const t = String(text || '').slice(0, 50000);
  for (const r of RULES) if (matchesUnnegated(r, t)) hits.push({ id: r.id, w: r.w, flag: r.flag });
  for (const r of extraRules || []) {
    try {
      if (r && r.pattern && new RegExp(r.pattern, 'iu').test(t)) hits.push({ id: 'x', w: Math.max(0, Math.min(50, Number(r.weight) || 15)), flag: String(r.flag || 'Matches a known scam pattern') });
    } catch { /* bad admin regex: skip */ }
  }
  const hosts = hostsIn(t);
  if (pageUrl) {
    try { hosts.unshift(new URL(pageUrl).hostname.toLowerCase()); } catch { /* not a URL */ }
  }
  const seen = new Set();
  for (const h of hosts) for (const f of checkHost(h)) {
    if (!seen.has(f.flag)) { seen.add(f.flag); hits.push(f); }
  }
  const score = Math.min(100, hits.reduce((s, h) => s + h.w, 0));
  const verdict = score >= 55 ? 'scam' : score >= 25 ? 'suspicious' : 'safe';
  return { score, verdict, hits, hosts };
}

export function guessScamType(hits) {
  const ids = new Set(hits.map((h) => h.id));
  if (ids.has('family')) return 'family_emergency';
  if (ids.has('prize')) return 'lottery_prize';
  if (ids.has('giftcard') || ids.has('crypto')) return ids.has('job') ? 'job_fee' : 'investment_crypto';
  if (ids.has('remote')) return 'tech_support';
  if (ids.has('job')) return 'job_fee';
  if (ids.has('money') && (ids.has('locked') || ids.has('fee'))) return 'fake_remittance';
  if (ids.has('lookalike') || ids.has('creds') || ids.has('otp')) return 'phishing';
  if (ids.has('fee')) return 'delivery_fee';
  return hits.length ? 'other' : 'none';
}
