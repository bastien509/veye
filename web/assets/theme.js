/* Runs in <head> so the saved theme applies before the page paints. */
(function () {
  var root = document.documentElement;
  root.classList.remove('no-js');
  try {
    var t = localStorage.getItem('veye-theme');
    if (t === 'light' || t === 'dark') root.setAttribute('data-theme', t);
  } catch (e) { /* storage blocked: follow the system theme */ }
})();
