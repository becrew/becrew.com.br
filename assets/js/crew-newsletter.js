/**
 * Crew - newsletter forms (footer on every page + blog sidebar).
 * Posts to assets/php/newsletter.php, which e-mails contato@becrew.com.br.
 * With JS: sends in the background and shows an inline message.
 * Without JS: the form posts normally and the PHP redirects back with ?newsletter=ok|invalid|error.
 */
(function () {
  var t = window.crewT || function (k, pt) { return pt; };
  var messages = {
    sending: t('newsletter.sending', 'Enviando…'),
    ok: t('newsletter.ok', 'Pronto! Você vai receber nossos insights no seu e-mail.'),
    invalid: t('newsletter.invalid', 'Digite um e-mail válido.'),
    error: t('newsletter.error', 'Não foi possível enviar agora. Tente novamente em instantes.')
  };

  function messageEl(form) {
    var el = form.nextElementSibling;
    if (!el || !el.classList.contains('crew-news-msg')) {
      el = document.createElement('p');
      el.className = 'crew-news-msg';
      el.setAttribute('role', 'status');
      el.setAttribute('aria-live', 'polite');
      form.parentNode.insertBefore(el, form.nextSibling);
    }
    return el;
  }

  function show(form, status) {
    var el = messageEl(form);
    el.textContent = messages[status] || messages.error;
    el.dataset.status = status;
  }

  var forms = document.querySelectorAll('form.tft-footer-newsletter');
  forms.forEach(function (form) {
    var lang = form.querySelector('input[name="idioma"]');
    if (lang) lang.value = window.crewLang || 'pt';

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = form.querySelector('input[type="email"]');
      if (!input || !input.value.trim() || !input.checkValidity()) {
        show(form, 'invalid');
        if (input) input.focus();
        return;
      }
      var button = form.querySelector('button[type="submit"]');
      if (button) button.disabled = true;
      show(form, 'sending');

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' }
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          var status = data && data.status;
          show(form, status === 'ok' || status === 'invalid' ? status : 'error');
          if (status === 'ok') form.reset();
        })
        .catch(function () { show(form, 'error'); })
        .then(function () { if (button) button.disabled = false; });
    });
  });

  // Fallback (no-JS submit came back with ?newsletter=...)
  var status = new URLSearchParams(window.location.search).get('newsletter');
  var target = document.getElementById('newsletter') || forms[0];
  if (status && target) show(target, status);
})();
