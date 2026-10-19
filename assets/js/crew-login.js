/* Crew - client area login (area-do-cliente.html).
   - Validates e-mail and password inline.
   - Show / hide password.
   - Destination is configured on the <form>:
       action="..."          -> the form is submitted to that endpoint (real login backend);
       data-login-url="..."  -> the visitor is sent to the client tool's login page;
       neither set           -> an honest notice says access is being activated (no fake login). */
(function () {
  var form = document.getElementById('crew-login-form');
  if (!form) return;
  var t = window.crewT || function (k, pt) { return pt; };
  var notice = form.querySelector('.crew-login__notice');
  var submit = form.querySelector('.crew-login__submit');

  var toggle = form.querySelector('[data-password-toggle]');
  var pass = form.querySelector('#login-password');
  if (toggle && pass) {
    toggle.addEventListener('click', function () {
      var show = pass.type === 'password';
      pass.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', String(show));
      toggle.setAttribute('aria-label', show ? t('login.hide', 'Ocultar senha') : t('login.show', 'Mostrar senha'));
      toggle.innerHTML = '<i data-lucide="' + (show ? 'eye-off' : 'eye') + '"></i>';
      if (window.lucide) window.lucide.createIcons(toggle);
      pass.focus();
    });
  }

  function check(input) {
    var field = input.closest('.crew-field');
    var ok = input.checkValidity();
    field.classList.toggle('is-invalid', !ok);
    input.setAttribute('aria-invalid', String(!ok));
    return ok;
  }
  var inputs = [].slice.call(form.querySelectorAll('input[required]'));
  inputs.forEach(function (input) {
    var err = form.querySelector('[data-error-for="' + input.id + '"]');
    if (err) { err.id = input.id + '-error'; input.setAttribute('aria-describedby', err.id); }
    input.addEventListener('blur', function () { if (input.value) check(input); });
    input.addEventListener('input', function () { if (input.closest('.crew-field').classList.contains('is-invalid')) check(input); });
  });

  form.addEventListener('submit', function (e) {
    var valid = inputs.map(check).every(Boolean);
    if (!valid) {
      e.preventDefault();
      var first = form.querySelector('.is-invalid input');
      if (first) first.focus();
      return;
    }
    var action = form.getAttribute('action');
    if (action && window.fetch && window.FormData) {
      // Real login endpoint: send in the background and show the result inline.
      e.preventDefault();
      submit.classList.add('is-loading');
      notice.hidden = true;
      fetch(action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'server' }; }); })
        .then(function (res) {
          if (res.ok) { window.location.href = res.redirect || 'painel.php'; return; }
          fail(res.error);
        })
        .catch(function () { fail('server'); });
      return;
    }
    if (action) { submit.classList.add('is-loading'); return; }
    e.preventDefault();
    var url = form.getAttribute('data-login-url');
    if (url) { submit.classList.add('is-loading'); window.location.href = url; return; }
    showNotice(t('login.pending', 'Seu acesso online está sendo ativado. Enquanto isso, fale com o seu time na Crew pelo <a href="mailto:contato@becrew.com.br">contato@becrew.com.br</a> ou pelo <a href="https://wa.me/5519936196249" target="_blank" rel="noopener noreferrer">WhatsApp</a>.'));
  });

  function showNotice(html, isError) {
    notice.innerHTML = html;
    notice.classList.toggle('is-error', !!isError);
    notice.hidden = false;
  }
  function fail(code) {
    submit.classList.remove('is-loading');
    var msg = code === 'limit'
      ? t('login.limit', 'Muitas tentativas seguidas. Aguarde alguns minutos e tente de novo.')
      : code === 'invalid'
        ? t('login.invalid', 'E-mail ou senha incorretos. Confira os dados e tente de novo.')
        : t('login.server', 'Não conseguimos entrar agora. Tente de novo em instantes.');
    showNotice(msg, true);
    var card = form.closest('.crew-login__card');
    if (card) { card.classList.remove('is-denied'); void card.offsetWidth; card.classList.add('is-denied'); }
    if (code === 'invalid') { pass.value = ''; pass.focus(); }
  }

  // Fallback without JavaScript: login.php sends back ?erro=1 or ?erro=limite
  var erro = /[?&]erro=([^&]+)/.exec(location.search);
  if (erro) fail(erro[1] === 'limite' ? 'limit' : 'invalid');
})();
