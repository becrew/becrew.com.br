/**
 * Crew - Entregas da área do cliente: temas e artes para aprovar, de todas as frentes da empresa.
 * Os dados vêm da ponte (CrewArea, em crew-area.js); o site não guarda nada.
 * Todo texto que vem da Plataforma entra por textContent, nunca por innerHTML.
 */
(function () {
  var raiz = document.querySelector('[data-entregas]');
  if (!raiz || !window.CrewArea) return;

  var T = window.crewT || function (k, f) { return f; };
  var EN = window.crewLang === 'en';
  var statusEl = raiz.querySelector('[data-entregas-status]');
  var estado = { entregas: [], frentes: [], carregado: false, filtroEstado: 'todos', filtroFrente: 'todas' };
  var ocupado = false;      // enviando uma decisão
  var formAberto = false;   // alguém está escrevendo um pedido de ajuste: não redesenhar por baixo
  var ultimaBusca = 0;

  /* Motivos enviados à Plataforma em português (a equipe lê em português); só o rótulo na tela troca de idioma. */
  var MOTIVOS = {
    tema: ['O tema não combina com a minha marca', 'A data não é boa', 'Prefiro outro formato', 'Informação errada (preço, plano, local)', 'Já fizemos algo parecido', 'Outro motivo'],
    arte: ['Texto da arte', 'Cores ou visual', 'Imagem ou foto', 'Informação errada (preço, plano, local)', 'Não combina com a identidade da minha marca', 'Outro motivo']
  };

  function el(tag, cls, texto) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (texto != null) n.textContent = texto;
    return n;
  }
  function icone(nome) { var i = el('i'); i.setAttribute('data-lucide', nome); return i; }
  /* Chaves de tradução trazem HTML só em 'carregando'; aqui tudo vira texto puro. */
  function tx(chave, pt) {
    var v = T(chave, pt);
    var t = document.createElement('textarea'); t.innerHTML = v; return t.value;
  }

  /* ---------- datas ---------- */
  var tz = 'America/Sao_Paulo';
  var loc = EN ? 'en-US' : 'pt-BR';
  function partesData(iso) {
    if (!iso) return null;
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    if (m) return { a: +m[1], m: +m[2], d: +m[3] };
    var d = new Date(iso);
    if (isNaN(d.getTime())) return null;
    var p = new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' }).format(d).split('-');
    return { a: +p[0], m: +p[1], d: +p[2] };
  }
  function dataCurta(p) { return new Intl.DateTimeFormat(loc, { day: '2-digit', month: '2-digit', timeZone: 'UTC' }).format(new Date(Date.UTC(p.a, p.m - 1, p.d))); }
  function dataHora(iso) {
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat(loc, { timeZone: tz, day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(d);
  }
  function dataEntrega(e) { return partesData(e.postar_em) || partesData(e.data_planejada); }
  function chaveSemana(p) {
    var d = new Date(Date.UTC(p.a, p.m - 1, p.d));
    var dow = (d.getUTCDay() + 6) % 7; // segunda = 0
    d.setUTCDate(d.getUTCDate() - dow);
    return d.toISOString().slice(0, 10);
  }

  /* ---------- classificação ---------- */
  function situacao(e) {
    if (e.acao_cliente === 'tema') return 'tema';
    if (e.acao_cliente === 'arte') return 'arte';
    if (e.coluna === 'entregue') return 'entregue';
    if (e.tema != null && e.tema_estado === 'ajuste') return 'ajuste-tema';
    if (e.coluna === 'ajuste') return 'ajuste-arte';
    if (e.coluna === 'com_cliente' && e.tema != null && e.tema_estado !== 'aprovado') return 'aprove-tema';
    return 'producao';
  }
  function grupoFiltro(e) {
    var s = situacao(e);
    if (s === 'tema' || s === 'arte' || s === 'aprove-tema') return 'voce';
    if (s === 'ajuste-tema' || s === 'ajuste-arte') return 'ajuste';
    return s; // producao | entregue
  }
  var PILL = {
    'tema': ['ent.pill.tema', 'Tema esperando você', 'is-voce'],
    'arte': ['ent.pill.arte', 'Arte esperando você', 'is-voce'],
    'aprove-tema': ['ent.pill.tema', 'Tema esperando você', 'is-voce'],
    'ajuste-tema': ['ent.pill.ajuste.tema', 'Ajuste do tema pedido', 'is-ajuste'],
    'ajuste-arte': ['ent.pill.ajuste.arte', 'Ajuste da arte pedido', 'is-ajuste'],
    'producao': ['ent.pill.producao', 'Em produção', 'is-prod'],
    'entregue': ['ent.pill.entregue', 'Entregue', 'is-ok']
  };

  function nomeFrente(chave) {
    for (var i = 0; i < estado.frentes.length; i++) if (estado.frentes[i].chave === chave) return estado.frentes[i].nome;
    return chave || '';
  }

  /* ---------- passos ---------- */
  function passos(e) {
    var s = situacao(e), lista = [];
    var temaOk = e.tema == null || e.tema_estado === 'aprovado';
    var arteNoCliente = e.coluna === 'com_cliente' || e.coluna === 'ajuste' || e.coluna === 'entregue';
    if (e.tema != null) lista.push({ t: tx('ent.passo.tema', 'Tema'), est: temaOk ? 'ok' : 'now' });
    lista.push({ t: tx('ent.passo.arte', 'Arte'), est: !temaOk ? 'next' : (arteNoCliente ? 'ok' : 'now') });
    lista.push({ t: tx('ent.passo.aprov', 'Aprovação'), est: e.coluna === 'entregue' ? 'ok' : (temaOk && arteNoCliente ? 'now' : 'next') });
    lista.push({ t: tx('ent.passo.entregue', 'Entregue'), est: e.coluna === 'entregue' ? 'ok' : 'next' });
    var ol = el('ol', 'ent-passos');
    lista.forEach(function (p) {
      var li = el('li', 'ent-passo is-' + p.est, p.t);
      if (p.est === 'now') li.setAttribute('aria-current', 'step');
      ol.appendChild(li);
    });
    return ol;
  }

  /* ---------- blocos de decisão ---------- */
  function aviso(texto, cls) { var p = el('p', 'ent-nota ' + (cls || ''), texto); return p; }

  function blocoTema(e) {
    var b = el('div', 'ent-bloco');
    b.appendChild(el('span', 'ent-bloco__rotulo', tx('ent.bloco.tema', 'Tema')));
    b.appendChild(el('p', 'ent-bloco__texto', e.tema));
    return b;
  }

  function linkArquivo(v) {
    if (!v || typeof v.link !== 'string' || v.link.indexOf('https://') !== 0) return null;
    var a = el('a', 'crew-area-btn crew-area-btn--ghost ent-baixar');
    a.href = v.link; a.target = '_blank'; a.rel = 'noopener noreferrer';
    a.appendChild(icone('download'));
    a.appendChild(el('span', null, tx('ent.arquivo.baixar', 'Baixar arquivo') + (v.nome ? ' · ' + v.nome : '')));
    return a;
  }

  function botoesDecisao(e, tipo, versao, rodadas) {
    var caixa = el('div', 'ent-decisao');
    var msg = el('p', 'ent-erro'); msg.hidden = true; msg.setAttribute('role', 'alert');
    var linha = el('div', 'ent-botoes');
    var ok = el('button', 'crew-area-btn', tx('ent.btn.aprovar.' + tipo, tipo === 'tema' ? 'Aprovar tema' : 'Aprovar arte'));
    ok.type = 'button';
    var aj = el('button', 'crew-area-btn crew-area-btn--ghost', tx('ent.btn.ajuste.' + tipo, 'Pedir ajuste'));
    aj.type = 'button';
    linha.appendChild(ok); linha.appendChild(aj);
    caixa.appendChild(linha);
    caixa.appendChild(msg); // o aviso fica logo abaixo dos botões, sempre à vista
    if (rodadas >= 2) caixa.appendChild(aviso(tx('ent.rodadas.limite', 'O contrato prevê 2 rodadas de ajuste e as duas já foram usadas. Você ainda pode aprovar.'), 'is-alerta'));
    var form = montarForm(tipo);
    form.raiz.hidden = true;
    caixa.appendChild(form.raiz);

    function erro(t) { msg.textContent = t || ''; msg.hidden = !t; }
    function travar(sim) { ok.disabled = aj.disabled = form.enviar.disabled = sim; }

    function enviar(decisao, motivos, sugestao) {
      if (ocupado) return;
      ocupado = true; travar(true); erro('');
      window.CrewArea.decidir({ tarefa: e.id, tipo: tipo, versao: versao, decisao: decisao, motivos: motivos, sugestao: sugestao })
        .then(function (r) {
          ocupado = false; formAberto = false;
          if (r.status === 200 && r.corpo && r.corpo.entrega) { trocar(r.corpo.entrega); return; }
          if (r.status === 409) { // o estado mudou: mostra o motivo e recarrega
            var m = r.corpo && typeof r.corpo.mensagem === 'string' ? r.corpo.mensagem : tx('ent.erro.salvar', 'Não foi possível salvar. Nada foi alterado. Tente de novo.');
            travar(false); erro(m); buscar(true, m); return;
          }
          travar(false); erro(tx('ent.erro.salvar', 'Não foi possível salvar. Nada foi alterado. Tente de novo.'));
        }, function (err) {
          ocupado = false; travar(false);
          erro(err && err.status === 404 ? tx('ent.erro.404', 'Esta entrega não está mais disponível.')
            : err && err.status === 429 ? tx('ent.erro.429', 'Muitas ações em pouco tempo. Espere um minuto e tente de novo.')
            : tx('ent.erro.salvar', 'Não foi possível salvar. Nada foi alterado. Tente de novo.'));
        });
    }

    ok.addEventListener('click', function () { enviar('aprovada', [], ''); });
    aj.addEventListener('click', function () {
      form.raiz.hidden = !form.raiz.hidden;
      formAberto = !form.raiz.hidden;
      if (formAberto) form.texto.focus();
    });
    form.cancelar.addEventListener('click', function () { form.raiz.hidden = true; formAberto = false; erro(''); });
    form.enviar.addEventListener('click', function () {
      var motivos = [];
      form.caixas.forEach(function (c) { if (c.checked) motivos.push(c.value); });
      var sug = form.texto.value.trim();
      if (!motivos.length && !sug) { erro(tx('ent.rej.falta', 'Escolha um motivo ou escreva uma sugestão.')); return; }
      enviar('ajuste', motivos, sug);
    });
    return caixa;
  }

  function montarForm(tipo) {
    var raiz = el('div', 'ent-form');
    raiz.appendChild(el('p', 'ent-form__titulo', tx('ent.rej.titulo.' + tipo, 'Por que não aprovar?')));
    var grade = el('div', 'ent-motivos');
    var caixas = [];
    MOTIVOS[tipo].forEach(function (pt, i) {
      var lab = el('label', 'ent-motivo');
      var c = document.createElement('input'); c.type = 'checkbox'; c.value = pt;
      lab.appendChild(c);
      lab.appendChild(el('span', null, tx('ent.motivo.' + tipo + '.' + (i + 1), pt)));
      grade.appendChild(lab); caixas.push(c);
    });
    raiz.appendChild(grade);
    var rot = el('label', 'ent-form__rotulo', tx('ent.rej.sugestao.' + tipo, 'O que você sugere?'));
    var texto = document.createElement('textarea');
    texto.rows = 3; texto.maxLength = 1000;
    texto.placeholder = tx('ent.rej.ph.' + tipo, '');
    rot.appendChild(texto);
    raiz.appendChild(rot);
    var linha = el('div', 'ent-botoes');
    var enviar = el('button', 'crew-area-btn', tx('ent.rej.enviar', 'Enviar')); enviar.type = 'button';
    var cancelar = el('button', 'crew-area-btn crew-area-btn--ghost', tx('ent.rej.cancelar', 'Cancelar')); cancelar.type = 'button';
    linha.appendChild(enviar); linha.appendChild(cancelar);
    raiz.appendChild(linha);
    return { raiz: raiz, texto: texto, caixas: caixas, enviar: enviar, cancelar: cancelar };
  }

  function blocoArte(e, s) {
    var b = el('div', 'ent-bloco');
    b.appendChild(el('span', 'ent-bloco__rotulo', tx('ent.bloco.arte', 'Arte')));
    var v = e.versao;
    if (s === 'producao') { b.appendChild(el('p', 'ent-bloco__texto', tx('ent.arte.producao', 'A Crew está produzindo a arte.'))); return b; }
    if (s === 'aprove-tema') { b.appendChild(el('p', 'ent-bloco__texto', tx('ent.arte.aprove.tema', 'Aprove o tema antes.'))); return b; }
    if (s === 'ajuste-arte') b.appendChild(el('p', 'ent-bloco__texto', tx('ent.arte.ajustando', 'A Crew está ajustando a arte.')));
    if (s === 'tema') { b.appendChild(el('p', 'ent-bloco__texto', tx('ent.arte.aprove.tema', 'Aprove o tema antes.'))); return b; }
    if (s === 'ajuste-tema') { b.appendChild(el('p', 'ent-bloco__texto', tx('ent.arte.aprove.tema', 'Aprove o tema antes.'))); return b; }
    if (v && (s === 'arte' || s === 'entregue')) {
      if (v.numero) b.appendChild(el('p', 'ent-bloco__meta', tx('ent.arte.versao', 'Versão') + ' ' + v.numero));
      var l = linkArquivo(v); if (l) b.appendChild(l);
    }
    if (s === 'entregue') {
      var ult = e.ultima_decisao;
      if (ult && ult.decisao === 'tacita') b.appendChild(el('p', 'ent-nota is-ok', tx('ent.arte.tacita', 'Aprovada automaticamente')));
      else b.appendChild(el('p', 'ent-nota is-ok', tx('ent.arte.aprovada', 'Arte aprovada')));
    }
    if (s === 'arte') {
      var p = e.tacita_ate ? partesData(e.tacita_ate) : null;
      if (p) b.appendChild(el('p', 'ent-nota is-prazo', tx('ent.arte.prazo', 'Responda até') + ' ' + dataCurta(p) + ' · ' + tx('ent.arte.prazo.fim', 'senão ela é aprovada automaticamente.')));
      b.appendChild(botoesDecisao(e, 'arte', v.numero, e.rodadas_arte || 0));
    }
    return b;
  }

  /* ---------- cartão ---------- */
  function cartao(e) {
    var s = situacao(e);
    var c = el('article', 'ent-card');
    c.setAttribute('data-id', e.id);
    var topo = el('header', 'ent-card__topo');
    var etiquetas = el('div', 'ent-etiquetas');
    etiquetas.appendChild(el('span', 'ent-frente', nomeFrente(e.frente)));
    if (e.produto) etiquetas.appendChild(el('span', 'ent-produto', e.produto));
    topo.appendChild(etiquetas);
    var pill = PILL[s];
    var pl = el('span', 'ent-pill ' + pill[2], tx(pill[0], pill[1]));
    topo.appendChild(pl);
    c.appendChild(topo);
    c.appendChild(el('h3', 'ent-card__titulo', e.titulo || ''));

    var quando = null;
    if (e.postar_em && dataHora(e.postar_em)) quando = tx('ent.agendado', 'Agendado para') + ' ' + dataHora(e.postar_em);
    else if (partesData(e.data_planejada)) quando = tx('ent.planejado', 'Previsto para') + ' ' + dataCurta(partesData(e.data_planejada));
    if (quando) { var q = el('p', 'ent-quando'); q.appendChild(icone('calendar')); q.appendChild(el('span', null, quando)); c.appendChild(q); }

    c.appendChild(passos(e));

    if (e.tema != null) {
      var bt = blocoTema(e);
      if (s === 'tema') bt.appendChild(botoesDecisao(e, 'tema', e.tema_versao || 1, e.rodadas_tema || 0));
      else if (e.tema_estado === 'aprovado') bt.appendChild(el('p', 'ent-nota is-ok', tx('ent.tema.aprovado', 'Tema aprovado')));
      else if (e.tema_estado === 'ajuste') bt.appendChild(el('p', 'ent-nota is-ajuste', tx('ent.tema.ajuste.espera', 'Ajuste pedido. A Crew está revisando o tema.')));
      c.appendChild(bt);
    }
    c.appendChild(blocoArte(e, s));
    return c;
  }

  /* ---------- lista, filtros e grupos ---------- */
  var ESTADOS = [['todos', 'ent.filtro.todos', 'Todas'], ['voce', 'ent.filtro.voce', 'Esperando você'], ['ajuste', 'ent.filtro.ajuste', 'Ajuste pedido'], ['producao', 'ent.filtro.producao', 'Em produção'], ['entregue', 'ent.filtro.entregue', 'Entregues']];

  function chip(texto, ativo, aoClicar, contagem) {
    var b = el('button', 'ent-chip' + (ativo ? ' is-ativo' : ''), texto);
    b.type = 'button'; b.setAttribute('aria-pressed', ativo ? 'true' : 'false');
    if (contagem != null) b.appendChild(el('b', null, String(contagem)));
    b.addEventListener('click', aoClicar);
    return b;
  }

  function desenhar() {
    var alvo = raiz.querySelector('[data-entregas-lista]');
    if (!alvo) { alvo = el('div', 'ent-lista'); alvo.setAttribute('data-entregas-lista', ''); raiz.appendChild(alvo); }
    alvo.textContent = '';
    if (!estado.entregas.length) { alvo.appendChild(el('p', 'crew-entregas__status', tx('ent.vazio', 'Ainda não há entregas para mostrar.'))); return; }

    var filtros = el('div', 'ent-filtros');
    var contagem = { todos: estado.entregas.length, voce: 0, ajuste: 0, producao: 0, entregue: 0 };
    estado.entregas.forEach(function (e) { contagem[grupoFiltro(e)]++; });
    var linhaEstado = el('div', 'ent-chips'); linhaEstado.setAttribute('role', 'group');
    ESTADOS.forEach(function (x) {
      linhaEstado.appendChild(chip(tx(x[1], x[2]), estado.filtroEstado === x[0], function () { estado.filtroEstado = x[0]; desenhar(); }, contagem[x[0]]));
    });
    filtros.appendChild(linhaEstado);
    if (estado.frentes.length > 1) {
      var linhaFrente = el('div', 'ent-chips'); linhaFrente.setAttribute('role', 'group');
      linhaFrente.appendChild(chip(tx('ent.filtro.todas', 'Todas as frentes'), estado.filtroFrente === 'todas', function () { estado.filtroFrente = 'todas'; desenhar(); }));
      estado.frentes.forEach(function (f) {
        linhaFrente.appendChild(chip(f.nome, estado.filtroFrente === f.chave, function () { estado.filtroFrente = f.chave; desenhar(); }));
      });
      filtros.appendChild(linhaFrente);
    }
    alvo.appendChild(filtros);

    var vis = estado.entregas.filter(function (e) {
      return (estado.filtroEstado === 'todos' || grupoFiltro(e) === estado.filtroEstado) && (estado.filtroFrente === 'todas' || e.frente === estado.filtroFrente);
    });
    if (!vis.length) { alvo.appendChild(el('p', 'crew-entregas__status', tx('ent.vazio.filtro', 'Nenhuma entrega com este filtro.'))); }
    else {
      var grupos = {}, ordem = [];
      vis.forEach(function (e) {
        var p = dataEntrega(e), k = p ? chaveSemana(p) : 'sem';
        if (!grupos[k]) { grupos[k] = []; ordem.push(k); }
        grupos[k].push(e);
      });
      ordem.sort(function (a, b) { return a === 'sem' ? 1 : b === 'sem' ? -1 : (a < b ? -1 : 1); });
      ordem.forEach(function (k) {
        var sec = el('section', 'ent-semana');
        var tit = k === 'sem' ? tx('ent.semdata', 'Sem data') : tx('ent.semana', 'Semana de') + ' ' + dataCurta(partesData(k));
        sec.appendChild(el('h2', 'ent-semana__titulo', tit));
        grupos[k].sort(function (a, b) {
          var pa = dataEntrega(a), pb = dataEntrega(b);
          var ka = pa ? pa.a * 10000 + pa.m * 100 + pa.d : 0, kb = pb ? pb.a * 10000 + pb.m * 100 + pb.d : 0;
          return ka - kb;
        }).forEach(function (e) { sec.appendChild(cartao(e)); });
        alvo.appendChild(sec);
      });
    }
    if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(alvo);
  }

  /* ---------- carga ---------- */
  function mostrarStatus(texto) { statusEl.textContent = texto || ''; statusEl.hidden = !texto; }

  function trocar(nova) {
    for (var i = 0; i < estado.entregas.length; i++) if (estado.entregas[i].id === nova.id) { estado.entregas[i] = nova; break; }
    desenhar();
  }

  function buscar(forcar, mensagem) {
    if (!forcar && (ocupado || formAberto)) return;
    ultimaBusca = Date.now();
    window.CrewArea.carregar().then(function (c) {
      estado.entregas = c.entregas; estado.frentes = Array.isArray(c.frentes) ? c.frentes : []; estado.carregado = true;
      mostrarStatus(mensagem || '');
      desenhar();
      if (mensagem) statusEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, function () {
      var msg = tx('area.erro.carregar', 'Não foi possível carregar agora. Tente de novo.');
      mostrarStatus(msg);
      if (!estado.carregado) {
        var alvo = raiz.querySelector('[data-entregas-lista]');
        if (!alvo) { alvo = el('div', 'ent-lista'); alvo.setAttribute('data-entregas-lista', ''); raiz.appendChild(alvo); }
        alvo.textContent = '';
        var b = el('button', 'crew-area-btn crew-area-btn--ghost', tx('area.tentar', 'Tentar de novo')); b.type = 'button';
        b.addEventListener('click', function () { mostrarStatus(tx('area.carregando', 'Carregando…')); buscar(true); });
        alvo.appendChild(b);
      }
    });
  }

  /* Atualiza a cada 60 s só com a aba visível, e ao voltar para ela. */
  setInterval(function () { if (document.visibilityState === 'visible') buscar(false); }, 60000);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && Date.now() - ultimaBusca > 15000) buscar(false);
  });

  buscar(true);
})();
