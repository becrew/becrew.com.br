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

  /* Número de entregas esperando o cliente, ao lado de "Entregas" na barra lateral. */
  function marcarMenu(n) {
    var b = document.querySelector('[data-nav-esperando]');
    if (!b) return;
    b.textContent = String(n);
    b.hidden = n === 0;
  }

  function carregar() {
    return fetch(API + '?acao=entregas', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (res) {
        if (res.status === 401) { window.location.href = 'area-do-cliente.html'; return new Promise(function () {}); }
        return lerJson(res).then(function (corpo) {
          if (res.status !== 200 || !corpo || !Array.isArray(corpo.entregas)) throw { status: res.status };
          marcarMenu(Number(corpo.esperando) || 0);
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

  /* ---- Início: os números das entregas e a lista do que espera o cliente ---- */
  var inicio = document.querySelector('[data-inicio]');
  if (!inicio) return;
  var nums = {};
  inicio.querySelectorAll('[data-inicio-num]').forEach(function (n) { nums[n.getAttribute('data-inicio-num')] = n; });
  var texto = inicio.querySelector('[data-inicio-texto]');
  var lista = inicio.querySelector('[data-inicio-lista]');
  var vazio = inicio.querySelector('[data-inicio-vazio]');
  var tentarBox = inicio.querySelector('[data-inicio-tentar-box]');
  var tentar = inicio.querySelector('[data-inicio-tentar]');
  var T = window.crewT || function (k, f) { return f; };
  var loc = document.documentElement.lang === 'en' ? 'en-US' : 'pt-BR';

  /* Mesmo agrupamento dos filtros da tela de Entregas (crew-entregas.js). */
  function grupo(e) {
    if (e.acao_cliente) return 'voce';
    if (e.coluna === 'entregue') return 'entregue';
    if ((e.tema != null && e.tema_estado === 'ajuste') || e.coluna === 'ajuste') return 'ajuste';
    if (e.coluna === 'com_cliente' && e.tema != null && e.tema_estado !== 'aprovado') return 'voce';
    return 'producao';
  }
  function dataCurta(e) {
    var iso = e.postar_em || e.data_planejada;
    var m = typeof iso === 'string' && iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return '';
    return new Intl.DateTimeFormat(loc, { day: '2-digit', month: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(+m[1], +m[2] - 1, +m[3])));
  }
  function el(tag, cls, txt) { var n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; }

  function mostrar(c) {
    tentarBox.hidden = true;
    var frentes = {};
    (c.frentes || []).forEach(function (f) { if (f && f.chave) frentes[f.chave] = f.nome || f.chave; });
    var conta = { voce: 0, ajuste: 0, producao: 0, entregue: 0 };
    var pendentes = [];
    c.entregas.forEach(function (e) {
      if (!e || typeof e !== 'object') return;
      var g = grupo(e); conta[g]++;
      if (e.acao_cliente) pendentes.push(e);
    });
    conta.voce = Number(c.esperando) || pendentes.length;
    Object.keys(nums).forEach(function (k) { nums[k].textContent = String(conta[k] || 0); });
    var n = conta.voce;
    texto.textContent = n === 0 ? T('area.inicio.zero', 'Nada esperando você. Está tudo em dia.')
      : n === 1 ? T('area.inicio.um', 'entrega esperando a sua aprovação.')
      : T('area.inicio.varios', 'entregas esperando a sua aprovação.');

    lista.textContent = '';
    pendentes.slice(0, 6).forEach(function (e) {
      var li = el('li');
      var a = el('a', 'app-linha'); a.href = 'entregas.php';
      var info = el('span');
      info.appendChild(el('span', 'app-linha__titulo', e.titulo || ''));
      info.appendChild(el('span', 'app-linha__meta', [frentes[e.frente] || e.frente, e.produto].filter(Boolean).join(' · ')));
      a.appendChild(info);
      a.appendChild(el('span', 'app-linha__tipo', e.acao_cliente === 'tema' ? T('area.inicio.tipo.tema', 'Aprovar tema') : T('area.inicio.tipo.arte', 'Aprovar arte')));
      a.appendChild(el('span', 'app-linha__data', dataCurta(e)));
      li.appendChild(a); lista.appendChild(li);
    });
    vazio.hidden = pendentes.length > 0;
    vazio.textContent = T('area.inicio.zero', 'Nada esperando você. Está tudo em dia.');
  }
  function falhou() {
    Object.keys(nums).forEach(function (k) { nums[k].textContent = '–'; });
    texto.textContent = '';
    lista.textContent = '';
    vazio.hidden = false;
    vazio.textContent = T('area.erro.carregar', 'Não foi possível carregar agora. Tente de novo.');
    tentarBox.hidden = false;
  }

  function buscar() { carregar().then(mostrar, falhou); }
  tentar.addEventListener('click', function () {
    tentarBox.hidden = true;
    vazio.hidden = true;
    texto.textContent = T('area.carregando', 'Carregando…');
    buscar();
  });
  buscar();
})();
