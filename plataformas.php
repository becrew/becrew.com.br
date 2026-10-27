<?php
/**
 * Crew - Produtos: vitrine das plataformas da Crew, por categoria (as mesmas frentes do CRM: Gaming, Agência, Consultoria, Tech),
 * com o que o cliente já tem e o que pode contratar, e a pontuação: cada produto contratado soma XP, e o XP dá nível e benefícios.
 * O catálogo, os níveis e a conta dos pontos ficam em assets/php/area/produtos.php.
 * Abre também para usuário sem empresa da área do cliente.
 * Só abre para quem entrou pela área do cliente (a porta de login e o menu ficam em assets/php/area/layout.php).
 */
require __DIR__ . '/assets/php/auth.php';
require __DIR__ . '/assets/php/area/layout.php';
$perfil = crew_area_exigir_login(false);

$categorias = crew_area_categorias();
$produtos = crew_area_produtos();
$xp = crew_area_xp($produtos);
$nivel = crew_area_nivel($xp);

crew_area_abrir('plataformas', $perfil, ['assets/css/crew-entregas.css', 'assets/css/crew-produtos.css']); // crew-entregas.css: os filtros (chips)
?>
<section class="xp-painel" id="pontos" data-nivel="<?= $nivel['atual'][0] ?>" aria-labelledby="xp-titulo">
<div class="xp-painel__resumo">
<img class="xp-painel__selo" src="assets/img/niveis/<?= $nivel['atual'][0] ?>.png" alt="" width="48" height="48">
<div class="xp-painel__info">
<span class="xp-painel__kicker" data-i18n="area.xp.seus">Seus pontos de experiência</span>
<h2 class="xp-painel__titulo" id="xp-titulo"><span class="xp-painel__num"><?= crew_area_num($xp) ?></span> XP <span class="xp-painel__nivel"><span data-i18n="area.xp.nivel">Nível</span> <span data-i18n="area.nivel.<?= $nivel['atual'][0] ?>"><?= $nivel['atual'][1] ?></span></span></h2>
<span class="app-nivel__barra xp-painel__barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $nivel['pct'] ?>"><span style="width: <?= $nivel['pct'] ?>%"></span></span>
<?php if ($nivel['proximo']) { ?>
<p class="xp-painel__falta"><strong><?= crew_area_num($nivel['falta']) ?> XP</strong> <span data-i18n="area.xp.para">para</span> <span data-i18n="area.nivel.<?= $nivel['proximo'][0] ?>"><?= $nivel['proximo'][1] ?></span></p>
<?php } else { ?>
<p class="xp-painel__falta" data-i18n="area.xp.maximo">Você está no nível máximo.</p>
<?php } ?>
</div>
</div>
<div class="xp-painel__como">
<h3 class="xp-painel__sub" data-i18n="area.xp.como.titulo">Como funciona</h3>
<p data-i18n="area.xp.como.texto">Cada produto da Crew que a sua empresa contrata soma pontos de experiência (XP). <strong>Quanto mais pontos, mais alto o seu nível e mais benefícios você tem.</strong> Os benefícios de um nível continuam valendo nos níveis seguintes.</p>
</div>
<ol class="xp-niveis">
<?php foreach (crew_area_niveis() as $i => [$chave, $rotulo, $min, $beneficio]) {
    $estado = $i < $nivel['indice'] ? 'is-feito' : ($i === $nivel['indice'] ? 'is-atual' : 'is-bloqueado');
?>
<li class="xp-nivel <?= $estado ?>" data-nivel="<?= $chave ?>">
<span class="xp-nivel__icone"><img src="assets/img/niveis/<?= $chave ?>.png" alt="" width="56" height="56"><?php if ($estado === 'is-bloqueado') { ?><i data-lucide="lock"></i><?php } ?></span>
<span class="xp-nivel__nome" data-i18n="area.nivel.<?= $chave ?>"><?= $rotulo ?></span>
<span class="xp-nivel__min"><?= crew_area_num($min) ?> XP</span>
<span class="xp-nivel__beneficio" data-i18n="area.nivel.<?= $chave ?>.beneficio"><?= $beneficio ?></span>
<?php if ($estado === 'is-atual') { ?><em class="xp-nivel__voce" data-i18n="area.xp.voce">Você está aqui</em><?php } ?>
</li>
<?php } ?>
</ol>
</section>
<nav class="prod-cats" aria-label="Categorias" data-i18n-attr="aria-label:area.prod.categorias">
<button type="button" class="prod-cat is-ativo" data-prod-cat="todas" aria-pressed="true"><i data-lucide="layout-grid"></i><span data-i18n="area.prod.todas">Todas</span><b><?= count($produtos) ?></b></button>
<?php foreach ($categorias as $c => [$rotulo, $icone]) { ?>
<button type="button" class="prod-cat" data-prod-cat="<?= $c ?>" aria-pressed="false"><i data-lucide="<?= $icone ?>"></i><span data-i18n="area.prod.cat.<?= $c ?>"><?= $rotulo ?></span><b><?= count(array_filter($produtos, fn($p) => $p['categoria'] === $c)) ?></b></button>
<?php } ?>
</nav>
<div class="prod-barra">
<p class="prod-barra__conta"><strong data-prod-conta><?= count($produtos) ?></strong> <span data-i18n="area.prod.produtos">produtos</span></p>
<div class="ent-chips" role="group" aria-label="Filtrar produtos" data-i18n-attr="aria-label:area.prod.filtrar">
<button type="button" class="ent-chip is-ativo" data-prod-filtro="todos" aria-pressed="true"><span data-i18n="area.prod.todos">Todos</span></button>
<button type="button" class="ent-chip" data-prod-filtro="ativo" aria-pressed="false"><span data-i18n="area.prod.ativos">Contratados</span></button>
<button type="button" class="ent-chip" data-prod-filtro="contratar" aria-pressed="false"><span data-i18n="area.prod.contratar.filtro">Para contratar</span></button>
</div>
</div>
<section class="prod-grade" aria-label="Produtos" data-i18n-attr="aria-label:area.nav.plataformas">
<?php foreach ($produtos as $p) {
    $ativo = $p['link'] !== null;
    $breve = !$ativo && !empty($p['breve']);
    $situacao = $ativo ? 'ativo' : ($breve ? 'breve' : 'contratar');
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
<?php if ($ativo) { ?>
<span class="prod-card__xp is-ganho"><i data-lucide="zap"></i><span><?= crew_area_num($p['xp']) ?> XP</span> <span data-i18n="area.xp.ganho">ganhos</span></span>
<?php } elseif ($breve) { ?>
<span class="prod-card__xp is-breve"><i data-lucide="clock"></i><span data-i18n="area.prod.breve">Em breve</span></span>
<?php } else { ?>
<span class="prod-card__xp"><i data-lucide="zap"></i><span>+<?= crew_area_num($p['xp']) ?> XP</span></span>
<?php } ?>
</div>
<div class="prod-card__corpo">
<span class="prod-card__cat" data-i18n="area.prod.cat.<?= $p['categoria'] ?>"><?= $catRotulo ?></span>
<h2 class="prod-card__nome" data-i18n="painel.<?= $p['chave'] ?>.title"><?= $p['nome'] ?></h2>
<p class="prod-card__texto" data-i18n="painel.<?= $p['chave'] ?>.text"><?= $p['texto'] ?></p>
<div class="prod-card__pe">
<?php if ($ativo) { ?>
<span class="prod-status is-ativo"><i data-lucide="check-circle"></i><span data-i18n="area.prod.ativo">Contratado</span></span>
<a class="crew-area-btn" href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener noreferrer"><span data-i18n="area.prod.acessar">Acessar</span><i data-lucide="arrow-up-right"></i></a>
<?php } elseif ($breve) { ?>
<span class="prod-status"><i data-lucide="clock"></i><span data-i18n="area.prod.breve.texto">Chega em breve</span></span>
<span class="prod-card__xpbreve"><i data-lucide="zap"></i>+<?= crew_area_num($p['xp']) ?> XP</span>
<?php } else { ?>
<span class="prod-status"><i data-lucide="lock"></i><span data-i18n="area.prod.nao">Não contratado</span></span>
<a class="crew-area-btn crew-area-btn--ghost" href="contato.html"><span data-i18n="area.prod.quero">Quero contratar</span><i data-lucide="arrow-right"></i></a>
<?php } ?>
</div>
</div>
</article>
<?php } ?>
</section>
<p class="prod-vazio" data-prod-vazio hidden data-i18n="area.prod.vazio">Nenhum produto nesta categoria ainda.</p>
<script>
/* Filtros da vitrine: categoria (abas) e situação (contratados / para contratar), combinados. */
(function () {
  var cat = 'todas', sit = 'todos';
  var cards = document.querySelectorAll('[data-prod]');
  var vazio = document.querySelector('[data-prod-vazio]');
  var conta = document.querySelector('[data-prod-conta]');
  function aplicar() {
    var n = 0;
    cards.forEach(function (c) {
      var ok = (cat === 'todas' || c.getAttribute('data-cat') === cat) && (sit === 'todos' || c.getAttribute('data-prod') === sit);
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
</script>
<?php crew_area_fechar(['painel']); ?>
