/**
 * Crew - i18n
 *
 * Portuguese is the source language and lives directly in the HTML.
 * English strings live in assets/js/lang/en/*.js (window.CREW_I18N, keyed by id).
 *
 *   <h2 data-i18n="home.hero.title">Texto em PT</h2>            -> innerHTML
 *   <input data-i18n-attr="placeholder:key;aria-label:key2">     -> attributes
 *   crewT('key', 'Texto PT')                                     -> strings used by JS
 *
 * Switching language stores the choice and reloads, so JS-driven content
 * (sliders, tabs) is always built in the right language.
 */
(function () {
  var STORAGE_KEY = 'crew-lang';
  var dict = window.CREW_I18N || {};
  var lang = 'pt';

  try {
    var params = new URLSearchParams(window.location.search);
    var fromUrl = params.get('lang');
    if (fromUrl === 'pt' || fromUrl === 'en') localStorage.setItem(STORAGE_KEY, fromUrl);
    lang = localStorage.getItem(STORAGE_KEY) === 'en' ? 'en' : 'pt';
  } catch (e) { /* storage blocked: stay in PT */ }

  window.crewLang = lang;
  window.crewT = function (key, fallback) {
    return lang === 'en' && dict[key] != null ? dict[key] : fallback;
  };
  document.documentElement.lang = lang === 'en' ? 'en' : 'pt-BR';

  function apply() {
    if (lang === 'en') {
      document.querySelectorAll('[data-i18n]').forEach(function (el) {
        var v = dict[el.getAttribute('data-i18n')];
        if (v != null) el.innerHTML = v;
      });
      document.querySelectorAll('[data-i18n-attr]').forEach(function (el) {
        el.getAttribute('data-i18n-attr').split(';').forEach(function (pair) {
          var p = pair.split(':');
          var v = dict[(p[1] || '').trim()];
          if (p[0] && v != null) el.setAttribute(p[0].trim(), v);
        });
      });
    }

    document.querySelectorAll('[data-lang-switch]').forEach(function (btn) {
      var target = btn.getAttribute('data-lang-switch');
      var active = target === lang;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      btn.addEventListener('click', function () {
        if (target === lang) return;
        try { localStorage.setItem(STORAGE_KEY, target); } catch (e) {}
        var url = new URL(window.location.href);
        url.searchParams.set('lang', target);
        window.location.href = url.toString();
      });
    });
  }

  // Loaded at the end of <body>, before main.js: the DOM is ready and
  // main.js will read crewT() when it builds sliders/tabs.
  apply();
})();
