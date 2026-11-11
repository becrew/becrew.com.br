<?php
/**
 * Crew - Produtos: vitrine do que a Crew vende, em três abas (Rafael, 2026-11-11): Serviços, Games e SVAs,
 * com o que o cliente já tem e o que pode contratar. Os pontos e níveis ficam numa página própria (pontos.php).
 * Os SVAs aparecem num cartão só, que abre um popup com todos (Rafael, 2026-11-11); cada SVA continua no catálogo.
 * O catálogo fica em assets/php/area/produtos.php (com o placar e os benefícios desligados na etapa 1).
 * Abre também para usuário sem empresa da área do cliente.
 * Só abre para quem entrou pela área do cliente (a porta de login e o menu ficam em assets/php/area/layout.php).
 */
require __DIR__ . '/assets/php/auth.php';
require __DIR__ . '/assets/php/area/layout.php';
$perfil = crew_area_exigir_login(false);

$categorias = crew_area_categorias();
$visiveis = array_values(array_filter(crew_area_produtos(), fn($p) => empty($p['oculto'])));
$svas = array_values(array_filter($visiveis, fn($p) => $p['categoria'] === 'svas'));
$cartoes = array_values(array_filter($visiveis, fn($p) => $p['categoria'] !== 'svas'));
$grupo = $svas ? crew_area_svas_cartao() : null;
$algumContratado = count(array_filter($visiveis, 'crew_area_contratado')) > 0;
$nCartoes = count($cartoes) + ($grupo ? 1 : 0);

function crew_produtos_situacao(array $p): string {
    if (crew_area_contratado($p)) return 'ativo';
    return empty($p['breve']) ? 'contratar' : 'breve';
}

/* Pé de um produto (cartão ou item do popup): contratado, em breve ou para contratar. */
function crew_produtos_pe(array $p, string $situacao): void {
    if ($situacao === 'ativo') { ?>
<span class="prod-status is-ativo"><i data-lucide="check-circle"></i><span data-i18n="area.prod.ativo">Contratado</span></span>
<a class="crew-area-btn" href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener noreferrer"><span data-i18n="area.prod.acessar">Acessar</span><i data-lucide="arrow-up-right"></i></a>
<?php } elseif ($situacao === 'breve') { ?>
<span class="prod-status"><i data-lucide="clock"></i><span data-i18n="area.prod.breve.texto">Chega em breve</span></span>
<?php if (CREW_AREA_PLACAR) { ?><span class="prod-card__xpbreve"><i data-lucide="zap"></i>+<?= crew_area_num($p['xp']) ?> XP</span><?php } ?>
<?php } else { ?>
<span class="prod-status"><i data-lucide="lock"></i><span data-i18n="area.prod.nao">Não contratado</span></span>
<a class="crew-area-btn crew-area-btn--ghost" href="contato.html"><span data-i18n="area.prod.quero">Quero contratar</span><i data-lucide="arrow-right"></i></a>
<?php }
}

crew_area_abrir('produtos', $perfil, ['assets/css/crew-entregas.css', 'assets/css/crew-produtos.css']); // crew-entregas.css: os filtros (chips)
?>
<nav class="prod-cats" aria-label="Categorias" data-i18n-attr="aria-label:area.prod.categorias">
<button type="button" class="prod-cat is-ativo" data-prod-cat="todas" aria-pressed="true"><i data-lucide="layout-grid"></i><span data-i18n="area.prod.todas">Todas</span><b><?= $nCartoes ?></b></button>
<?php foreach ($categorias as $c => [$rotulo, $icone]) {
    $n = $c === 'svas' ? ($grupo ? 1 : 0) : count(array_filter($cartoes, fn($p) => $p['categoria'] === $c));
    if ($n === 0) continue; // aba sem produto à vista some ?>
<button type="button" class="prod-cat" data-prod-cat="<?= $c ?>" aria-pressed="false"><i data-lucide="<?= $icone ?>"></i><span data-i18n="area.prod.cat.<?= $c ?>"><?= $rotulo ?></span><b><?= $n ?></b></button>
<?php } ?>
</nav>
<div class="prod-barra">
<p class="prod-barra__conta"><strong data-prod-conta><?= $nCartoes ?></strong> <span data-i18n="area.prod.produtos">produtos</span></p>
<div class="ent-chips" role="group" aria-label="Filtrar produtos" data-i18n-attr="aria-label:area.prod.filtrar">
<button type="button" class="ent-chip is-ativo" data-prod-filtro="todos" aria-pressed="true"><span data-i18n="area.prod.todos">Todos</span></button>
<?php if ($algumContratado) { ?>
<button type="button" class="ent-chip" data-prod-filtro="ativo" aria-pressed="false"><span data-i18n="area.prod.ativos">Contratados</span></button>
<?php } ?>
<button type="button" class="ent-chip" data-prod-filtro="contratar" aria-pressed="false"><span data-i18n="area.prod.contratar.filtro">Para contratar</span></button>
</div>
</div>
<section class="prod-grade" aria-label="Produtos" data-i18n-attr="aria-label:area.nav.produtos">
<?php foreach ($cartoes as $p) {
    $ativo = crew_area_contratado($p);
    $situacao = crew_produtos_situacao($p);
    $breve = $situacao === 'breve';
    [$catRotulo] = $categorias[$p['categoria']];
?>
<article class="prod-card is-<?= $situacao ?>" data-prod="<?= $situacao ?>" data-cat="<?= $p['categoria'] ?>" style="--prod-cor: <?= $p['cor'] ?>">
<div class="prod-card__foto<?= $p['foto'] === null ? ' is-marca' : '' ?>">
<?php if ($p['foto'] !== null) { ?>
<img alt="" src="<?= $p['foto'] ?>" loading="lazy">
<?php } else { ?>
<span class="prod-card__marca" aria-hidden="true"><i data-lucide="<?= $p['icone'] ?>"></i><span><?= $p['nome'] ?></span></span>
<?php } ?>
<span class="prod-card__tipo"><i data-lucide="<?= $p['tipo'][2] ?>"></i><span data-i18n="<?= $p['tipo'][0] ?>"><?= $p['tipo'][1] ?></span></span>
<?php if ($ativo && CREW_AREA_PLACAR) { ?>
<span class="prod-card__xp is-ganho"><i data-lucide="zap"></i><span><?= crew_area_num($p['xp']) ?> XP</span> <span data-i18n="area.xp.ganho">ganhos</span></span>
<?php } elseif ($breve) { ?>
<span class="prod-card__xp is-breve"><i data-lucide="clock"></i><span data-i18n="area.prod.breve">Em breve</span></span>
<?php } elseif (!$ativo && CREW_AREA_PLACAR) { ?>
<span class="prod-card__xp"><i data-lucide="zap"></i><span>+<?= crew_area_num($p['xp']) ?> XP</span></span>
<?php } ?>
</div>
<div class="prod-card__corpo">
<span class="prod-card__cat" data-i18n="area.prod.cat.<?= $p['categoria'] ?>"><?= $catRotulo ?></span>
<h2 class="prod-card__nome" data-i18n="painel.<?= $p['chave'] ?>.title"><?= $p['nome'] ?></h2>
<p class="prod-card__texto" data-i18n="painel.<?= $p['chave'] ?>.text"><?= $p['texto'] ?></p>
<div class="prod-card__pe">
<?php crew_produtos_pe($p, $situacao); ?>
</div>
</div>
</article>
<?php } ?>
<?php if ($grupo) {
    // O cartão aparece nos filtros de situação se algum SVA estiver nela (ex.: "Para contratar" mostra o cartão se algum SVA está à venda).
    $sitGrupo = implode(' ', array_unique(array_map('crew_produtos_situacao', $svas)));
    [$catRotulo] = $categorias['svas'];
?>
<article class="prod-card is-grupo" data-prod="<?= $sitGrupo ?>" data-cat="svas" data-dialogo-abrir="svas-dialogo" style="--prod-cor: <?= $grupo['cor'] ?>">
<div class="prod-card__foto">
<img alt="" src="<?= $grupo['foto'] ?>" loading="lazy">
</div>
<div class="prod-card__corpo">
<span class="prod-card__cat" data-i18n="area.prod.cat.svas"><?= $catRotulo ?></span>
<h2 class="prod-card__nome" data-i18n="painel.svas.title"><?= $grupo['nome'] ?></h2>
<p class="prod-card__texto" data-i18n="painel.svas.text"><?= $grupo['texto'] ?></p>
<div class="prod-card__pe">
<span class="prod-status"><i data-lucide="layers"></i><span><?= count($svas) ?> <span data-i18n="area.prod.svas.qtd">serviços</span></span></span>
<button type="button" class="crew-area-btn" data-dialogo-abrir="svas-dialogo" aria-haspopup="dialog"><span data-i18n="area.prod.svas.ver">Conhecer os SVAs</span><i data-lucide="arrow-right"></i></button>
</div>
</div>
</article>
<?php } ?>
</section>
<p class="prod-vazio" data-prod-vazio hidden data-i18n="area.prod.vazio">Nenhum produto nesta categoria ainda.</p>
<?php if ($grupo) { ?>
<dialog class="prod-dialogo" id="svas-dialogo" aria-labelledby="svas-dialogo-titulo">
<div class="prod-dialogo__caixa">
<div class="prod-dialogo__topo">
<div>
<span class="prod-card__cat" data-i18n="area.prod.cat.svas"><?= $categorias['svas'][0] ?></span>
<h2 class="prod-dialogo__titulo" id="svas-dialogo-titulo" data-i18n="area.prod.svas.titulo">SVAs da Crew</h2>
<p class="prod-dialogo__intro" data-i18n="area.prod.svas.intro">Serviços de valor adicionado para o seu provedor incluir nos planos e oferecer mais aos assinantes.</p>
</div>
<button type="button" class="prod-dialogo__fechar" data-dialogo-fechar aria-label="Fechar" data-i18n-attr="aria-label:area.prod.fechar"><i data-lucide="x"></i></button>
</div>
<ul class="prod-svas">
<?php foreach ($svas as $p) { $situacao = crew_produtos_situacao($p); ?>
<li class="prod-sva is-<?= $situacao ?>" style="--prod-cor: <?= $p['cor'] ?>">
<img class="prod-sva__logo" alt="" src="<?= $p['foto'] ?>" loading="lazy">
<div class="prod-sva__corpo">
<h3 class="prod-sva__nome" data-i18n="painel.<?= $p['chave'] ?>.title"><?= $p['nome'] ?></h3>
<p class="prod-sva__texto" data-i18n="painel.<?= $p['chave'] ?>.text"><?= $p['texto'] ?></p>
<div class="prod-card__pe">
<?php crew_produtos_pe($p, $situacao); ?>
</div>
</div>
</li>
<?php } ?>
</ul>
</div>
</dialog>
<?php } ?>
<script>
/* Filtros da vitrine: categoria (abas) e situação (contratados / para contratar), combinados.
   data-prod pode ter mais de uma situação (o cartão dos SVAs junta as de todos). */
(function () {
  var cat = 'todas', sit = 'todos';
  var cards = document.querySelectorAll('[data-prod]');
  var vazio = document.querySelector('[data-prod-vazio]');
  var conta = document.querySelector('[data-prod-conta]');
  function aplicar() {
    var n = 0;
    cards.forEach(function (c) {
      var sits = ' ' + c.getAttribute('data-prod') + ' ';
      var ok = (cat === 'todas' || c.getAttribute('data-cat') === cat) && (sit === 'todos' || sits.indexOf(' ' + sit + ' ') !== -1);
      c.hidden = !ok; if (ok) n++;
    });
    conta.textContent = String(n);
    vazio.hidden = n > 0;
  }
  function grupo(sel, aoEscolher) {
    var botoes = document.querySelectorAll(sel);
    botoes.forEach(function (b) {
      b.addEventListener('click', function () {
        botoes.forEach(function (x) { x.classList.toggle('is-ativo', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        aoEscolher(b); aplicar();
      });
    });
  }
  grupo('[data-prod-cat]', function (b) { cat = b.getAttribute('data-prod-cat'); });
  grupo('[data-prod-filtro]', function (b) { sit = b.getAttribute('data-prod-filtro'); });
})();

/* Popup dos SVAs: <dialog> modal (o resto da página fica inerte e o foco fica dentro dele).
   Abre pelo cartão ou pelo botão; fecha com Esc, com o X e com clique fora da caixa; o foco volta para quem abriu. */
(function () {
  var dialogo = document.getElementById('svas-dialogo');
  if (!dialogo || typeof dialogo.showModal !== 'function') return;
  var quemAbriu = null;
  function abrir(origem) {
    if (dialogo.open) return;
    quemAbriu = origem;
    dialogo.showModal();
    document.documentElement.classList.add('tem-dialogo');
    var x = dialogo.querySelector('[data-dialogo-fechar]'); if (x) x.focus();
  }
  document.querySelectorAll('[data-dialogo-abrir="svas-dialogo"]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (ev.target.closest('a')) return;
      ev.stopPropagation();
      var botao = el.matches('button') ? el : el.querySelector('button[data-dialogo-abrir]');
      abrir(botao || el);
    });
  });
  dialogo.querySelector('[data-dialogo-fechar]').addEventListener('click', function () { dialogo.close(); });
  dialogo.addEventListener('click', function (ev) { if (ev.target === dialogo) dialogo.close(); }); // clique no fundo, fora da caixa
  dialogo.addEventListener('close', function () {
    document.documentElement.classList.remove('tem-dialogo');
    if (quemAbriu) quemAbriu.focus();
  });
})();
</script>
<?php crew_area_fechar(['painel']); ?>
