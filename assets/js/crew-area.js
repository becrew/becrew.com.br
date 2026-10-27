/**
 * Crew - área do cliente: acesso à ponte (assets/php/area/api.php) e a página Início.
 * O navegador só fala com a ponte do próprio site; a empresa vem da sessão, nunca daqui.
 *   CrewArea.carregar()  -> Promise com {entregas, frentes, esperando}; rejeita com {status}
 *   CrewArea.decidir(corpo) -> Promise com {status, corpo} para 200/409; rejeita com {status}
 */
(function () {
  var API = 'assets/php/area/api.php';

  function lerJson(res) {
    return res.text().then(function (txt) {
      try { return JSON.parse(txt); } catch (e) { return null; }
    });
  }

  function carregar() {
    return fetch(API + '?acao=entregas', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (res) {
        if (res.status === 401) { window.location.href = 'area-do-cliente.html'; return new Promise(function () {}); }
        return lerJson(res).then(function (corpo) {
          if (res.status !== 200 || !corpo || !Array.isArray(corpo.entregas)) throw { status: res.status };
          return corpo;
        });
      }, function () { throw { status: 0 }; });
  }

  function decidir(corpo) {
    return fetch(API + '?acao=decidir', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
      body: JSON.stringify(corpo)
    }).then(function (res) {
      if (res.status === 401) { window.location.href = 'area-do-cliente.html'; return new Promise(function () {}); }
      return lerJson(res).then(function (resp) {
        if (res.status === 200 || res.status === 409) return { status: res.status, corpo: resp || {} };
        throw { status: res.status };
      });
    }, function () { throw { status: 0 }; });
  }

  window.CrewArea = { carregar: carregar, decidir: decidir };

  /* ---- Início: quantas entregas esperam o cliente ---- */
  var inicio = document.querySelector('[data-inicio]');
  if (!inicio) return;
  var num = inicio.querySelector('[data-inicio-num]');
  var texto = inicio.querySelector('[data-inicio-texto]');
  var tentar = inicio.querySelector('[data-inicio-tentar]');
  var T = window.crewT || function (k, f) { return f; };

  function mostrar(n, erro) {
    if (erro) {
      num.textContent = '–';
      texto.textContent = T('area.erro.carregar', 'Não foi possível carregar agora. Tente de novo.');
      tentar.hidden = false;
      return;
    }
    tentar.hidden = true;
    num.textContent = String(n);
    texto.textContent = n === 0 ? T('area.inicio.zero', 'Nada esperando você. Está tudo em dia.')
      : n === 1 ? T('area.inicio.um', 'entrega esperando a sua aprovação.')
      : T('area.inicio.varios', 'entregas esperando a sua aprovação.');
  }

  function buscar() {
    carregar().then(function (c) { mostrar(Number(c.esperando) || 0, false); },
      function () { mostrar(0, true); });
  }
  tentar.addEventListener('click', function () {
    num.textContent = '–';
    texto.textContent = T('area.carregando', 'Carregando…');
    buscar();
  });
  buscar();
})();
