/* ==========================================================================
   Crew - Landing page "Diagnóstico gratuito para provedores"
   Lê window.CREW_CONFIG (config.js). Sem dependências.
   Páginas: <body data-page="lp" data-origem="meta|google">, data-page="obrigado", data-page="privacidade".
   ========================================================================== */
(function () {
  'use strict';
  var C = window.CREW_CONFIG || {};
  var body = document.body;
  var PAGE = body.getAttribute('data-page') || 'lp';
  var ORIGEM = body.getAttribute('data-origem') || '';
  var filled = function (v) { return typeof v === 'string' && v.trim() !== '' && v.indexOf('[PREENCHER') === -1; };
  var $$ = function (sel, root) { return [].slice.call((root || document).querySelectorAll(sel)); };
  var store = {
    get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) { /* private mode */ } }
  };
  var uuid = function () {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16);
    });
  };

  /* ------------------------------------------------------------------------
     1. Tracking: GTM if configured, otherwise GA4 / Google Ads / Meta Pixel directly.
        Empty IDs load nothing and never throw.
     ------------------------------------------------------------------------ */
  window.dataLayer = window.dataLayer || [];
  var useGTM = filled(C.GTM_ID);
  function loadScript(src) { var s = document.createElement('script'); s.async = true; s.src = src; document.head.appendChild(s); }

  if (useGTM) {
    window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
    loadScript('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(C.GTM_ID));
  } else {
    var gtagId = filled(C.GA4_ID) ? C.GA4_ID : (filled(C.GADS_CONVERSION_ID) ? C.GADS_CONVERSION_ID : '');
    /* A tag do Google (gtag.js) já vem colada no <head> de cada página.
       Se ela já existe, não carrega o gtag.js de novo nem repete o config do Google Ads. */
    var hasTag = typeof window.gtag === 'function';
    if (gtagId && hasTag) {
      if (filled(C.GA4_ID)) window.gtag('config', C.GA4_ID);
    } else if (gtagId) {
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      if (filled(C.GA4_ID)) window.gtag('config', C.GA4_ID);
      if (filled(C.GADS_CONVERSION_ID)) window.gtag('config', C.GADS_CONVERSION_ID);
      loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gtagId));
    }
    if (filled(C.META_PIXEL_ID)) {
      /* Meta Pixel base code */
      (function (f, b, e, v, n, t, s) {
        if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
        if (!f._fbq) f._fbq = n; n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
        t = b.createElement(e); t.async = true; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
      })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      window.fbq('init', C.META_PIXEL_ID);
      window.fbq('track', 'PageView');
    }
  }

  /* Sends an event to the dataLayer (GTM) and, without GTM, straight to GA4. */
  function track(name, params) {
    params = params || {};
    var data = { event: name };
    for (var k in params) data[k] = params[k];
    window.dataLayer.push(data);
    if (!useGTM && typeof window.gtag === 'function' && filled(C.GA4_ID)) window.gtag('event', name, params);
  }

  /* ------------------------------------------------------------------------
     2. UTMs and click IDs: saved on the first visit of the session.
     ------------------------------------------------------------------------ */
  var TRACK_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'];
  (function captureParams() {
    var q = new URLSearchParams(location.search);
    TRACK_KEYS.forEach(function (k) {
      var v = q.get(k);
      if (v && !store.get('crew_' + k)) store.set('crew_' + k, v.slice(0, 300));
    });
  })();

  /* ------------------------------------------------------------------------
     3. WhatsApp links (number and message from config).
     ------------------------------------------------------------------------ */
  function waUrl(extra) {
    var msg = (C.WHATSAPP_MESSAGE || '') + (extra ? ' ' + extra : '');
    return 'https://wa.me/' + String(C.WHATSAPP_NUMBER || '').replace(/\D/g, '') + '?text=' + encodeURIComponent(msg);
  }
  function wireWhatsApp(root) {
    $$('[data-wa]', root).forEach(function (a) {
      if (a.__wa) return; a.__wa = true;
      if (filled(C.WHATSAPP_NUMBER)) a.href = waUrl(a.getAttribute('data-wa-extra') || '');
      a.target = '_blank'; a.rel = 'noopener';
      a.addEventListener('click', function () { track('whatsapp_click', { position: a.getAttribute('data-wa') || 'outro' }); });
    });
  }
  wireWhatsApp(document);

  /* ------------------------------------------------------------------------
     4. Spots left ("Restam X vagas").
     ------------------------------------------------------------------------ */
  var vagas = Number(C.VAGAS_RESTANTES);
  if (isFinite(vagas)) {
    var vagasText = vagas <= 0 ? 'Vagas deste mês esgotadas. Garanta seu lugar no próximo mês'
      : vagas === 1 ? 'Resta 1 vaga este mês' : 'Restam ' + vagas + ' vagas este mês';
    $$('[data-vagas]').forEach(function (el) { el.textContent = vagasText; });
  }
  if (isFinite(Number(C.VAGAS_TOTAL))) $$('[data-vagas-total]').forEach(function (el) { el.textContent = String(C.VAGAS_TOTAL); });

  /* ------------------------------------------------------------------------
     5. Footer / legal data from config (hidden while empty).
     ------------------------------------------------------------------------ */
  $$('[data-cfg]').forEach(function (el) {
    var key = el.getAttribute('data-cfg');
    var v = C[key];
    if (filled(v)) {
      var holder = el.querySelector('[data-cfg-value]') || el;
      holder.textContent = v;
      el.hidden = false;
    } else if (el.hasAttribute('data-cfg-optional')) {
      el.hidden = true;
    }
  });
  $$('[data-cfg-fallback]').forEach(function (el) {
    var key = el.getAttribute('data-cfg-fallback');
    if (filled(C[key])) el.hidden = true;
  });

  if (PAGE === 'lp') initLanding();
  if (PAGE === 'obrigado') initThanks();

  /* ======================================================================== */
  function initLanding() {
    /* Client logos (only with 3 or more) */
    var logos = Array.isArray(C.LOGOS_CLIENTES) ? C.LOGOS_CLIENTES.filter(function (l) { return l && filled(l.arquivo); }) : [];
    var strip = document.querySelector('[data-logos]');
    if (strip && logos.length >= 3) {
      var row = strip.querySelector('.logos__row');
      logos.forEach(function (l) {
        var img = document.createElement('img');
        img.src = l.arquivo; img.alt = l.nome || 'Logo de cliente'; img.loading = 'lazy'; img.decoding = 'async'; img.height = 34;
        row.appendChild(img);
      });
      strip.hidden = false;
    }

    /* Cases */
    var cases = Array.isArray(C.CASES) ? C.CASES.filter(function (c) { return c && filled(c.provedor) && filled(c.resultado); }) : [];
    var wrap = document.querySelector('[data-cases]');
    if (wrap && cases.length) {
      cases.forEach(function (c) { wrap.appendChild(caseCard(c)); });
      wrap.hidden = false;
      wireWhatsApp(wrap);
    }

    initReveal();
    initForms();
    initExitIntent();
    initStickyBar();
    initHeroCta();
    initScroll75();
  }

  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text) e.textContent = text; return e; }

  function caseCard(c) {
    var card = el('article', 'case');
    var head = el('div', 'case__head');
    if (filled(c.logo)) { var lg = el('img', 'case__logo'); lg.src = c.logo; lg.alt = 'Logo ' + c.provedor; lg.loading = 'lazy'; head.appendChild(lg); }
    var who = el('div'); who.appendChild(el('div', 'case__name', c.provedor)); if (filled(c.cidade)) who.appendChild(el('div', 'case__city', c.cidade));
    head.appendChild(who); card.appendChild(head);
    if (filled(c.desafio)) { var d = el('div'); d.appendChild(el('div', 'case__label', 'Desafio')); d.appendChild(el('p', '', c.desafio)); card.appendChild(d); }
    var r = el('div'); r.appendChild(el('div', 'case__label', 'Resultado'));
    var num = el('p', 'case__result'); num.appendChild(el('span', 'big-num', c.resultado)); r.appendChild(num);
    if (filled(c.prazo)) r.appendChild(el('p', 'case__prazo', c.prazo));
    card.appendChild(r);
    if (filled(c.video)) card.appendChild(videoBox(c));
    if (filled(c.depoimento)) {
      var q = el('blockquote', 'case__quote', '“' + c.depoimento + '”'); card.appendChild(q);
      var a = el('div', 'case__author');
      if (filled(c.foto)) { var f = el('img'); f.src = c.foto; f.alt = c.autor || ''; f.loading = 'lazy'; a.appendChild(f); }
      var t = el('div'); if (filled(c.autor)) t.appendChild(el('strong', '', c.autor)); if (filled(c.cargo)) t.appendChild(document.createTextNode(c.cargo));
      a.appendChild(t); card.appendChild(a);
    }
    return card;
  }

  /* Video loads only on click (thumbnail first). */
  function videoBox(c) {
    var box = el('div', 'case__video');
    var b = el('button'); b.type = 'button'; b.setAttribute('aria-label', 'Assistir ao depoimento de ' + (c.autor || c.provedor));
    var yt = /(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)([\w-]{11})/.exec(c.video);
    var thumb = yt ? 'https://i.ytimg.com/vi/' + yt[1] + '/hqdefault.jpg' : (filled(c.foto) ? c.foto : '');
    if (thumb) { var i = el('img'); i.src = thumb; i.alt = ''; i.loading = 'lazy'; b.appendChild(i); }
    var p = el('span', 'play'); p.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>'; b.appendChild(p);
    b.addEventListener('click', function () {
      var player;
      if (yt) { player = document.createElement('iframe'); player.src = 'https://www.youtube-nocookie.com/embed/' + yt[1] + '?autoplay=1&rel=0'; player.allow = 'autoplay; encrypted-media; picture-in-picture'; player.allowFullscreen = true; player.title = 'Depoimento em vídeo'; }
      else { player = document.createElement('video'); player.src = c.video; player.controls = true; player.autoplay = true; player.playsInline = true; }
      box.innerHTML = ''; box.appendChild(player);
    });
    box.appendChild(b);
    return box;
  }

  /* ------------------------------------------------------------------------
     Forms
     ------------------------------------------------------------------------ */
  function maskPhone(v) {
    var d = v.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) return d.length ? '(' + d : '';
    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
  }

  function leadScore(assinantes, cargo) {
    if (assinantes === 'ate-500') return 'frio';
    return (cargo === 'dono-socio' || cargo === 'gerente-comercial') ? 'quente' : 'morno';
  }

  var formStarted = false;

  function initForms() {
    $$('form[data-lead-form]').forEach(function (form) {
      var shownAt = Date.now();
      var submit = form.querySelector('[type="submit"]');
      var status = form.querySelector('.form__status');
      var phone = form.querySelector('input[name="whatsapp"]');

      if (phone) phone.addEventListener('input', function () { phone.value = maskPhone(phone.value); });

      var startOnce = function () {
        if (formStarted) return; formStarted = true;
        track('form_start', { form_position: form.getAttribute('data-position') });
      };
      form.addEventListener('focusin', startOnce);
      form.addEventListener('input', startOnce);

      $$('input, select', form).forEach(function (f) {
        f.addEventListener('blur', function () { if (f.value || f.type === 'checkbox') validate(f); });
        f.addEventListener('change', function () { if (f.closest('.field.is-invalid')) validate(f); });
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fields = $$('[data-validate]', form);
        var ok = fields.map(validate).every(Boolean);
        if (!ok) { var first = form.querySelector('.is-invalid input, .is-invalid select'); if (first) first.focus(); return; }

        /* Anti-spam: honeypot + 3 s minimum */
        if (form.querySelector('.hp input').value) return;
        if (Date.now() - shownAt < 3000) { showError(form, 'Confira seus dados e envie de novo em alguns segundos.'); return; }

        var v = function (n) { var f = form.elements[n]; return f ? String(f.value || '').trim() : ''; };
        var score = leadScore(v('assinantes'), v('cargo'));
        var eventId = uuid();
        var payload = {
          nome: v('nome'), whatsapp: v('whatsapp').replace(/\D/g, ''), provedor: v('provedor'), cidade: v('cidade'),
          assinantes: labelOf(form.elements.assinantes), cargo: labelOf(form.elements.cargo), email: v('email'),
          consentimento: form.elements.consentimento.checked,
          lead_score: score, origem: ORIGEM, form_position: form.getAttribute('data-position'),
          pagina: location.origin + location.pathname, data_hora: new Date().toISOString(), event_id: eventId
        };
        TRACK_KEYS.forEach(function (k) { payload[k] = store.get('crew_' + k) || ''; });

        submit.disabled = true;
        var label = submit.querySelector('[data-label]') || submit;
        var original = label.textContent;
        label.textContent = 'Enviando...';
        status.classList.remove('is-visible');

        send(payload).then(function () {
          store.set('crew_lead_' + eventId, JSON.stringify({ score: score, nome: payload.nome }));
          location.href = form.getAttribute('data-thanks') + '?score=' + score + '&eid=' + encodeURIComponent(eventId);
        }).catch(function () {
          submit.disabled = false; label.textContent = original;
          showError(form);
        });
      });

      function showError(form, msg) {
        status.innerHTML = '';
        status.appendChild(document.createTextNode(msg || 'Não conseguimos enviar. Fale com a gente pelo WhatsApp.'));
        if (!msg) {
          var a = document.createElement('a');
          a.className = 'btn btn--wa'; a.setAttribute('data-wa', 'erro');
          a.href = waUrl();
          a.innerHTML = WA_ICON + '<span>Chamar no WhatsApp</span>';
          status.appendChild(a);
          wireWhatsApp(status);
        }
        status.classList.add('is-visible');
      }
    });
  }

  function labelOf(select) { if (!select) return ''; var o = select.options[select.selectedIndex]; return o ? o.text : ''; }

  function validate(f) {
    var wrap = f.closest('.field');
    var ok = true;
    if (f.type === 'checkbox') ok = f.checked;
    else if (f.name === 'whatsapp') { var d = f.value.replace(/\D/g, ''); ok = d.length === 10 || d.length === 11; }
    else if (f.type === 'email') ok = !f.value || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.value);
    else ok = f.value.trim() !== '';
    if (wrap) wrap.classList.toggle('is-invalid', !ok);
    f.setAttribute('aria-invalid', String(!ok));
    return ok;
  }

  function send(payload) {
    var url = C.FORM_ENDPOINT;
    if (!filled(url)) return Promise.reject(new Error('no endpoint'));
    var ctrl = window.AbortController ? new AbortController() : null;
    var timer = setTimeout(function () { if (ctrl) ctrl.abort(); }, 15000);
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
      signal: ctrl ? ctrl.signal : undefined
    }).then(function (r) {
      clearTimeout(timer);
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.text().then(function (t) {
        try { var j = JSON.parse(t); if (j && j.ok === false) throw new Error('rejected'); } catch (e) { if (e.message === 'rejected') throw e; }
      });
    });
  }

  /* ------------------------------------------------------------------------
     Hero button: focus the hero form when it is visible (desktop),
     otherwise scroll to the final form.
     ------------------------------------------------------------------------ */
  function isShown(node) { return !!node && node.offsetParent !== null; }
  function initHeroCta() {
    $$('[data-to-form]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var heroForm = document.querySelector('.hero__form form');
        var target = isShown(heroForm) ? heroForm : document.getElementById('formulario');
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var first = target.querySelector('input:not([type="hidden"]):not([tabindex="-1"])');
        if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 450);
      });
    });
  }

  /* Sticky bar on mobile: after the hero, hidden while a form is on screen. */
  function initStickyBar() {
    var bar = document.querySelector('.stickybar');
    var hero = document.querySelector('.hero');
    if (!bar || !hero) return;
    var forms = $$('form[data-lead-form]');
    var onScreen = function (n) {
      if (!isShown(n)) return false;
      var r = n.getBoundingClientRect();
      return r.bottom > 0 && r.top < window.innerHeight;
    };
    var update = function () {
      var pastHero = hero.getBoundingClientRect().bottom <= 0;
      bar.classList.toggle('is-visible', pastHero && !forms.some(onScreen));
    };
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  }

  /* ------------------------------------------------------------------------
     Exit intent: invite the visitor to fill the form when they show they are leaving.
     - Desktop: the mouse leaves through the top of the window (tabs / address bar).
     - Touch: a fast scroll back up after reading a good part of the page.
     Never in the first 8 s, never twice per visit, never after the visitor started
     a form, and never while a form is on screen.
     ------------------------------------------------------------------------ */
  function initExitIntent() {
    var box = document.getElementById('exit-intent');
    if (!box || store.get('crew_exit_shown')) return;
    var card = box.querySelector('.exit__card');
    var armed = false, open = false, lastFocus = null;
    var forms = $$('form[data-lead-form]');
    setTimeout(function () { armed = true; }, 8000);

    var formOnScreen = function () {
      return forms.some(function (n) {
        if (!isShown(n)) return false;
        var r = n.getBoundingClientRect();
        return r.bottom > 0 && r.top < window.innerHeight;
      });
    };
    var canShow = function () { return armed && !open && !formStarted && !store.get('crew_exit_shown') && !formOnScreen(); };

    function show(trigger) {
      if (!canShow()) return;
      open = true;
      store.set('crew_exit_shown', '1');
      lastFocus = document.activeElement;
      box.hidden = false;
      void box.offsetWidth;
      setTimeout(function () { box.classList.add('is-open'); }, 16);
      document.documentElement.style.overflow = 'hidden';
      card.focus({ preventScroll: true });
      track('exit_intent_shown', { trigger: trigger });
      detach();
    }
    function hide() {
      if (!open) return;
      open = false;
      box.classList.remove('is-open');
      document.documentElement.style.overflow = '';
      setTimeout(function () { box.hidden = true; }, 300);
      if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
    }

    /* Desktop */
    var fine = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    var onMouseOut = function (e) { if (!e.relatedTarget && e.clientY <= 0) show('mouse'); };

    /* Touch: fast scroll up (more than 60% of the screen in under 0.7 s) after passing 35% of the page */
    var deepEnough = false, lastY = window.scrollY, lastT = Date.now(), upStartY = null, upStartT = 0;
    var onScroll = function () {
      var y = window.scrollY, t = Date.now(), h = document.documentElement.scrollHeight;
      if ((y + window.innerHeight) / h > 0.35) deepEnough = true;
      if (y < lastY) {
        if (upStartY === null || t - lastT > 250) { upStartY = lastY; upStartT = t; }
        if (deepEnough && upStartY - y > window.innerHeight * 0.6 && t - upStartT < 700) show('scroll');
      } else {
        upStartY = null;
      }
      lastY = y; lastT = t;
    };

    function detach() {
      document.removeEventListener('mouseout', onMouseOut);
      window.removeEventListener('scroll', onScroll);
    }
    if (fine) document.addEventListener('mouseout', onMouseOut);
    else window.addEventListener('scroll', onScroll, { passive: true });

    /* Close: ×, backdrop, Esc. The CTA closes first (capture) and then goes to the form. */
    $$('[data-exit-close]', box).forEach(function (b) { b.addEventListener('click', hide); });
    var cta = box.querySelector('[data-exit-cta]');
    if (cta) cta.addEventListener('click', function () { track('exit_intent_cta'); hide(); }, true);
    box.querySelector('[data-wa]').addEventListener('click', function () { setTimeout(hide, 50); });
    document.addEventListener('keydown', function (e) {
      if (!open) return;
      if (e.key === 'Escape') { e.preventDefault(); hide(); return; }
      if (e.key === 'Tab') {
        var f = $$('a[href], button', card);
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && (document.activeElement === first || document.activeElement === card)) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });

    /* For testing: CREW_EXIT.show() opens it right away (ignores the 8 s delay). */
    window.CREW_EXIT = { show: function () { armed = true; show('teste'); }, hide: hide };
  }

  /* Entrance animation below the hero: fade-up when the block reaches the screen.
     Hidden only after JS runs, and never with "reduce motion". */
  function initReveal() {
    var items = $$('[data-reveal]');
    if (!items.length || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
    document.documentElement.classList.add('js-reveal');
    var check = function () {
      var limit = window.innerHeight * 0.92;
      items = items.filter(function (n) {
        if (n.getBoundingClientRect().top < limit) { n.classList.add('is-in'); return false; }
        return true;
      });
      if (!items.length) { window.removeEventListener('scroll', check); window.removeEventListener('resize', check); }
    };
    window.addEventListener('scroll', check, { passive: true });
    window.addEventListener('resize', check);
    check();
  }

  function initScroll75() {
    var done = false;
    window.addEventListener('scroll', function () {
      if (done) return;
      var h = document.documentElement;
      if ((h.scrollTop + window.innerHeight) / h.scrollHeight >= .75) { done = true; track('scroll_75'); }
    }, { passive: true });
  }

  /* ======================================================================== */
  function initThanks() {
    var q = new URLSearchParams(location.search);
    var score = q.get('score');
    if (['quente', 'morno', 'frio'].indexOf(score) === -1) score = 'morno';
    var eid = q.get('eid') || uuid();
    body.setAttribute('data-score', score);
    $$('[data-score-show]').forEach(function (n) { n.hidden = n.getAttribute('data-score-show').split(' ').indexOf(score) === -1; });
    var video = document.querySelector('.welcome__video');
    if (video) video.addEventListener('play', function once() { video.removeEventListener('play', once); track('welcome_video_play', { lead_score: score }); });

    /* Fire conversions once per lead (a reload does not count twice). */
    var key = 'crew_conv_' + eid;
    if (store.get(key)) return;
    store.set(key, '1');

    if (score === 'frio') {
      track('lead_frio', { lead_score: score, event_id: eid });
      if (!useGTM && typeof window.fbq === 'function') window.fbq('trackCustom', 'lead_frio', { lead_score: score }, { eventID: eid });
      return;
    }
    track('generate_lead', { lead_score: score, event_id: eid });
    if (!useGTM) {
      if (typeof window.fbq === 'function') window.fbq('track', 'Lead', { lead_score: score }, { eventID: eid });
      if (typeof window.gtag === 'function' && filled(C.GADS_CONVERSION_ID) && filled(C.GADS_CONVERSION_LABEL)) {
        window.gtag('event', 'conversion', { send_to: C.GADS_CONVERSION_ID + '/' + C.GADS_CONVERSION_LABEL, transaction_id: eid });
      }
    }
  }

  var WA_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm5.8 14.03c-.24.68-1.42 1.3-1.96 1.35-.5.05-.98.24-3.3-.69-2.8-1.1-4.58-3.97-4.72-4.15-.14-.18-1.13-1.5-1.13-2.87 0-1.37.72-2.04.97-2.32.25-.28.55-.35.73-.35l.53.01c.17 0 .4-.06.62.48.24.56.8 1.94.87 2.08.07.14.12.3.02.48-.09.18-.14.3-.28.46-.14.16-.29.36-.42.48-.14.14-.28.29-.12.57.16.28.72 1.19 1.55 1.93 1.07.95 1.97 1.25 2.25 1.39.28.14.44.12.6-.07.16-.18.69-.81.88-1.09.18-.28.37-.23.62-.14.25.09 1.6.76 1.88.9.28.14.46.21.53.32.07.12.07.66-.17 1.33z"/></svg>';
})();
