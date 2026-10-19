/* Crew - client platforms hub (painel-cliente.html).
   Swipeable stack of cards: drag the top card sideways (mouse or touch), use the arrows,
   the keyboard (left / right on the focused deck) or the platform chips. */
(function () {
  var stage = document.querySelector('.crew-deck__stage');
  if (!stage) return;
  var cards = [].slice.call(stage.querySelectorAll('.crew-deck__card'));
  var chips = [].slice.call(document.querySelectorAll('.crew-deck__chip'));
  var counter = document.querySelector('[data-deck-current]');
  var n = cards.length, current = 0, busy = false;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var pad = function (v) { return (v < 10 ? '0' : '') + v; };

  // Position of each card in the stack (0 = top). Cards behind peek above, fanned left and right.
  var SLOTS = [
    { y: 0, r: 0, s: 1, o: 1 },
    { y: -34, r: -6, s: .94, o: 1 },
    { y: -64, r: 6, s: .88, o: .7 },
    { y: -80, r: 0, s: .82, o: 0 }
  ];

  function place(card, slot, x, extraR) {
    card.style.transform = 'translate(' + (x || 0) + 'px,' + slot.y + 'px) rotate(' + (slot.r + (extraR || 0)) + 'deg) scale(' + slot.s + ')';
    card.style.opacity = slot.o;
  }

  function layout() {
    cards.forEach(function (card, k) {
      var off = (k - current + n) % n;
      var slot = SLOTS[Math.min(off, SLOTS.length - 1)];
      card.style.zIndex = String(n - off);
      card.classList.toggle('is-top', off === 0);
      if (off !== 0) { var al = card.querySelector('.crew-deck__alert'); if (al) { al.classList.remove('is-open'); al.hidden = true; } }
      if (!card.classList.contains('is-leaving')) place(card, slot);
      var top = off === 0;
      card.setAttribute('aria-hidden', String(!top));
      if ('inert' in card) card.inert = !top;
      [].forEach.call(card.querySelectorAll('a, button'), function (a) { a.tabIndex = top ? 0 : -1; });
    });
    chips.forEach(function (c, i) { c.setAttribute('aria-current', String(i === current)); });
    if (counter) counter.textContent = pad(current + 1);
  }

  // Throw the top card off to one side, then bring the next one forward.
  function go(dir, throwSide) {
    if (busy || n < 2) return;
    var leaving = cards[current];
    current = (current + dir + n) % n;
    if (dir > 0 && !reduce) {
      busy = true;
      var side = throwSide || -1;
      leaving.classList.add('is-leaving');
      leaving.style.transform = 'translate(' + (side * 140) + '%, 40px) rotate(' + (side * 22) + 'deg) scale(.95)';
      leaving.style.opacity = '0';
      layout();
      setTimeout(function () {
        leaving.classList.remove('is-leaving');
        leaving.style.transition = 'none';
        layout();
        void leaving.offsetWidth;
        leaving.style.transition = '';
        busy = false;
      }, 420);
    } else {
      layout();
    }
  }

  function goTo(i) {
    if (i === current) return;
    current = i; layout();
  }

  // Drag / swipe on the top card
  var startX = 0, dx = 0, dragging = null;
  stage.addEventListener('pointerdown', function (e) {
    var card = e.target.closest('.crew-deck__card.is-top');
    if (!card || busy || e.target.closest('a, button') || (e.pointerType === 'mouse' && e.button !== 0)) return;
    dragging = card; startX = e.clientX; dx = 0;
    card.classList.add('is-dragging');
    card.setPointerCapture(e.pointerId);
  });
  stage.addEventListener('pointermove', function (e) {
    if (!dragging) return;
    dx = e.clientX - startX;
    place(dragging, SLOTS[0], dx, dx / 16);
  });
  function release() {
    if (!dragging) return;
    var card = dragging; dragging = null;
    card.classList.remove('is-dragging');
    if (Math.abs(dx) > 90) go(1, dx < 0 ? -1 : 1);
    else place(card, SLOTS[0]);
  }
  stage.addEventListener('pointerup', release);
  stage.addEventListener('pointercancel', release);

  stage.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight') { e.preventDefault(); go(1); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); }
  });
  [].forEach.call(document.querySelectorAll('.crew-deck__arrow'), function (b) {
    b.addEventListener('click', function () { go(Number(b.getAttribute('data-dir'))); });
  });
  chips.forEach(function (c) { c.addEventListener('click', function () { goTo(Number(c.getAttribute('data-go'))); }); });

  // "Em breve": the client does not have this platform. Shake the card and show a notice.
  function closeAlert(alert) {
    if (!alert || alert.hidden) return;
    alert.classList.remove('is-open');
    setTimeout(function () { alert.hidden = true; }, 250);
  }
  stage.addEventListener('click', function (e) {
    var soon = e.target.closest('[data-soon]');
    var close = e.target.closest('.crew-deck__alert-close');
    if (close) { closeAlert(close.closest('.crew-deck__alert')); soon && soon.focus(); return; }
    if (!soon) return;
    var card = soon.closest('.crew-deck__card');
    var alert = card.querySelector('.crew-deck__alert');
    [card, soon].forEach(function (el) {
      el.classList.remove('is-denied'); void el.offsetWidth; el.classList.add('is-denied');
    });
    setTimeout(function () { card.classList.remove('is-denied'); soon.classList.remove('is-denied'); }, 950);
    if (alert) {
      alert.hidden = false;
      var icon = alert.querySelector('.crew-deck__alert-icon');
      if (icon) { icon.style.animation = 'none'; void icon.offsetWidth; icon.style.animation = ''; }
      void alert.offsetWidth; setTimeout(function () { alert.classList.add('is-open'); }, 16);
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') [].forEach.call(stage.querySelectorAll('.crew-deck__alert.is-open'), closeAlert);
  });

  layout();
})();
