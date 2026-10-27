/**
 * Crew - casca da área logada: no celular a barra lateral vira gaveta (abre pelo botão da barra de cima,
 * fecha no X, no fundo escuro ou com Esc).
 */
(function () {
  var side = document.getElementById('app-side');
  var abrir = document.querySelector('[data-app-open]');
  var scrim = document.querySelector('.app-scrim');
  if (!side || !abrir || !scrim) return;

  function definir(aberto) {
    side.classList.toggle('is-open', aberto);
    scrim.hidden = !aberto;
    abrir.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    if (aberto) { var link = side.querySelector('.app-nav__link'); if (link) link.focus(); }
    else abrir.focus();
  }
  abrir.addEventListener('click', function () { definir(true); });
  document.querySelectorAll('[data-app-close]').forEach(function (b) { b.addEventListener('click', function () { definir(false); }); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && side.classList.contains('is-open')) definir(false); });
})();
