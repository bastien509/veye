/* Site chrome: theme, mobile menu, Gemini Nano status chip, copy buttons, scroll reveal. */
(function () {
  var root = document.documentElement;

  var themeBtn = document.getElementById('themeBtn');
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var dark = root.getAttribute('data-theme') === 'dark' ||
        (!root.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
      var next = dark ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('veye-theme', next); } catch (e) { /* ignore */ }
    });
  }

  var head = document.getElementById('siteHead');
  var menuBtn = document.getElementById('menuBtn');
  if (head && menuBtn) {
    menuBtn.addEventListener('click', function () {
      var open = head.classList.toggle('is-open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Live Gemini Nano status, straight from Chrome's Prompt API (no network).
  var chip = document.getElementById('nanoChip');
  if (chip) {
    var txt = chip.querySelector('.nano-txt');
    var set = function (cls, label) { chip.className = 'nano-chip ' + cls; txt.textContent = label; chip.title = label; };
    if (typeof window.LanguageModel === 'undefined') {
      set('is-cloud', 'No Gemini Nano · cloud fallback');
    } else {
      window.LanguageModel.availability({ expectedInputs: [{ type: 'text', languages: ['en'] }], expectedOutputs: [{ type: 'text', languages: ['en'] }] })
        .then(function (a) {
          if (a === 'available') set('is-ready', 'Gemini Nano ready');
          else if (a === 'downloadable' || a === 'downloading') set('is-dl', 'Gemini Nano downloadable');
          else set('is-cloud', 'No Gemini Nano · cloud fallback');
        })
        .catch(function () { set('is-cloud', 'No Gemini Nano · cloud fallback'); });
    }
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var done = function () {
      b.classList.add('is-done');
      var old = b.lastChild.textContent;
      b.lastChild.textContent = 'Copied';
      setTimeout(function () { b.classList.remove('is-done'); b.lastChild.textContent = old; }, 1400);
    };
    if (navigator.clipboard) navigator.clipboard.writeText(b.getAttribute('data-copy')).then(done, function () {});
  });

  var fill = document.querySelector('[data-fill-demo]');
  if (fill) {
    fill.addEventListener('click', function () {
      document.getElementById('lu').value = 'demo';
      document.getElementById('lp').value = 'VeyeDemo509';
      document.getElementById('lp').focus();
    });
  }

  var els = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && els.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px' });
    els.forEach(function (el) { io.observe(el); });
  } else {
    els.forEach(function (el) { el.classList.add('is-in'); });
  }
})();
