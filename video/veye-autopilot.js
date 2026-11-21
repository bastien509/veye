// Demo autopilot for recording the Veye video (local only, never deployed). It drives the real app with
// a visible cursor, one scene per ?scene=... URL. Styles are set through the CSSOM because Veye's CSP
// forbids inline <style>.
(() => {
  const params = new URLSearchParams(location.search);
  const scene = params.get('scene') || sessionStorage.getItem('demoNext') || '';
  sessionStorage.removeItem('demoNext');
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const $ = (s) => document.querySelector(s);
  const byText = (sel, text) => [...document.querySelectorAll(sel)].find((e) => e.textContent.trim().includes(text));
  async function waitFor(fn, timeout = 150000) {
    const end = performance.now() + timeout;
    while (performance.now() < end) { const v = fn(); if (v) return v; await sleep(200); }
    throw new Error('timeout');
  }
  const set = (el, st) => Object.assign(el.style, st);

  const cur = document.createElement('div');
  cur.innerHTML = '<svg viewBox="0 0 24 24" width="30" height="30"><path d="M4 2l16 9-7 1.6L9.6 20z" fill="#fff" stroke="#0b1533" stroke-width="1.6" stroke-linejoin="round"/></svg>';
  set(cur, { position: 'fixed', left: '0', top: '0', zIndex: '99999', pointerEvents: 'none', transition: 'transform .7s cubic-bezier(.3,.8,.3,1)', filter: 'drop-shadow(0 3px 6px rgba(0,0,0,.45))' });
  let cx = innerWidth * 0.62, cy = innerHeight * 0.7;
  const place = () => { cur.style.transform = `translate(${cx}px,${cy}px)`; };
  place();
  document.body.append(cur);

  async function moveTo(el) {
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    await sleep(700);
    const r = el.getBoundingClientRect();
    cx = r.left + Math.min(r.width * 0.5, 70); cy = r.top + r.height * 0.55; place();
    await sleep(800);
  }
  async function click(el, pause = 500) {
    await moveTo(el);
    const rp = document.createElement('div');
    set(rp, { position: 'fixed', left: `${cx - 4}px`, top: `${cy - 4}px`, width: '16px', height: '16px', borderRadius: '50%', border: '3px solid #F5C518', zIndex: '99998', pointerEvents: 'none' });
    document.body.append(rp);
    rp.animate([{ transform: 'scale(1)', opacity: 1 }, { transform: 'scale(4)', opacity: 0 }], { duration: 600 }).onfinish = () => rp.remove();
    el.click();
    await sleep(pause);
  }
  const scrollTo = async (el, block = 'start', wait = 1400) => { el.scrollIntoView({ block, behavior: 'smooth' }); await sleep(wait); };
  const btn = (text) => waitFor(() => byText('button', text), 30000);
  async function check() {
    await click(await waitFor(() => $('#checkBtn')), 300);
    await waitFor(() => $('#trace') && $('#trace').children.length > 0);
    await sleep(600);
    await scrollTo($('#result'), 'start', 5000);
  }
  async function mode(v) { const r = $(`input[name="mode"][value="${v}"]`); if (r) await click(r.closest('label') || r, 600); }
  async function lang(v) { const s = $('#lang'); await moveTo(s); s.value = v; s.dispatchEvent(new Event('change', { bubbles: true })); await sleep(700); }

  const scenes = {
    async landing() {
      await sleep(18000);
      await scrollTo($('#router'), 'start', 8000);
      await scrollTo($('#privacy'), 'start', 6000);
    },
    async login() {
      await sleep(1500);
      await click(await btn('Fill in the demo account'), 900);
      sessionStorage.setItem('demoNext', 'dash');
      await click(await waitFor(() => $('button[type="submit"]')), 300);
    },
    async dash() { await sleep(4500); await scrollTo($('#msg'), 'center', 3000); },
    async device() {
      await sleep(1200);
      await click(await waitFor(() => $('[data-sample="remit"]')), 900);
      await mode('smart'); await lang('en');
      await check();
      await scrollTo($('#trace'), 'center', 5500);
    },
    async kreyol() {
      await sleep(1200);
      await click(await waitFor(() => $('[data-sample="kreyol"]')), 900);
      await mode('smart'); await lang('ht');
      await check();
      await scrollTo($('#trace'), 'center', 5500);
    },
    async shot() {
      await sleep(1200);
      await click(await waitFor(() => $('[data-tab="shot"]')), 900);
      // A phishing SMS screenshot drawn in the page (invented content).
      const c = document.createElement('canvas'); c.width = 720; c.height = 900; const g = c.getContext('2d');
      g.fillStyle = '#f2f2f7'; g.fillRect(0, 0, 720, 900); g.fillStyle = '#111'; g.font = 'bold 34px Arial'; g.fillText('+1 (888) 410-2297', 40, 70);
      g.fillStyle = '#e5e5ea'; g.beginPath(); g.roundRect(40, 120, 600, 330, 28); g.fill(); g.fillStyle = '#111'; g.font = '30px Arial';
      ['USPS: Your package is on hold', 'because of an unpaid fee of $1.99.', 'Pay within 12 hours or it will be', 'returned to sender:', 'usps-redelivery-help.com/pay', 'Reply Y to receive the link.'].forEach((l, i) => g.fillText(l, 70, 180 + i * 44));
      const blob = await new Promise((r) => c.toBlob(r, 'image/png'));
      const input = $('#shot'); const dt = new DataTransfer(); dt.items.add(new File([blob], 'sms.png', { type: 'image/png' }));
      input.files = dt.files; input.dispatchEvent(new Event('change', { bubbles: true }));
      await sleep(2500);
      await mode('smart'); await lang('en');
      await check();
      await scrollTo($('#trace'), 'center', 5000);
    },
    async ext() { await sleep(4000); const cards = document.querySelectorAll('section.card'); if (cards[1]) await scrollTo(cards[1], 'center', 6000); },
    async close() { await scrollTo($('#privacy'), 'start', 6000); await scrollTo($('#judges'), 'center', 6000); },
  };

  window.addEventListener('load', async () => {
    const fn = scenes[scene];
    if (fn) { try { await fn(); } catch (e) { document.title = `demo error: ${e.message}`; } }
  });
})();
