<?php
/**
 * Crew - Produtos: vitrine do que a Crew vende, em três abas (Rafael, 2026-11-11): Serviços, Games e SVAs,
 * com o que o cliente já tem e o que pode contratar. O painel de pontos e níveis fica no Início (painel.php#pontos).
 * O catálogo fica em assets/php/area/produtos.php (com o placar e os benefícios desligados na etapa 1).
 * Abre também para usuário sem empresa da área do cliente.
 * Só abre para quem entrou pela área do cliente (a porta de login e o menu ficam em assets/php/area/layout.php).
 */
require __DIR__ . '/assets/php/auth.php';
require __DIR__ . '/assets/php/area/layout.php';
$perfil = crew_area_exigir_login(false);

$categorias = crew_area_categorias();
$produtos = array_values(array_filter(crew_area_produtos(), fn($p) => empty($p['oculto'])));
$algumContratado = count(array_filter($produtos, 'crew_area_contratado')) > 0;

crew_area_abrir('produtos', $perfil, ['assets/css/crew-entregas.css', 'assets/css/crew-produtos.css']); // crew-entregas.css: os filtros (chips)
?>
<nav class="prod-cats" aria-label="Categorias" data-i18n-attr="aria-label:area.prod.categorias">
<button type="button" class="prod-cat is-ativo" data-prod-cat="todas" aria-pressed="true"><i data-lucide="layout-grid"></i><span data-i18n="area.prod.todas">Todas</span><b><?= count($produtos) ?></b></button>
<?php foreach ($categorias as $c => [$rotulo, $icone]) { $n = count(array_filter($produtos, fn($p) => $p['categoria'] === $c)); if ($n === 0) continue; // aba sem produto à vista some ?>
<button type="button" class="prod-cat" data-prod-cat="<?= $c ?>" aria-pressed="false"><i data-lucide="<?= $icone ?>"></i><span data-i18n="area.prod.cat.<?= $c ?>"><?= $rotulo ?></span><b><?= $n ?></b></button>
<?php } ?>
</nav>
<div class="prod-barra">
<p class="prod-barra__conta"><strong data-prod-conta><?= count($produtos) ?></strong> <span data-i18n="area.prod.produtos">produtos</span></p>
<div class="ent-chips" role="group" aria-label="Filtrar produtos" data-i18n-attr="aria-label:area.prod.filtrar">
<button type="button" class="ent-chip is-ativo" data-prod-filtro="todos" aria-pressed="true"><span data-i18n="area.prod.todos">Todos</span></button>
<?php if ($algumContratado) { ?>
<button type="button" class="ent-chip" data-prod-filtro="ativo" aria-pressed="false"><span data-i18n="area.prod.ativos">Contratados</span></button>
<?php } ?>
<button type="button" class="ent-chip" data-prod-filtro="contratar" aria-pressed="false"><span data-i18n="area.prod.contratar.filtro">Para contratar</span></button>
</div>
</div>
<section class="prod-grade" aria-label="Produtos" data-i18n-attr="aria-label:area.nav.produtos">
<?php foreach ($produtos as $p) {
    $ativo = crew_area_contratado($p);
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
<?php if ($ativo) { ?>
<span class="prod-status is-ativo"><i data-lucide="check-circle"></i><span data-i18n="area.prod.ativo">Contratado</span></span>
<a class="crew-area-btn" href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener noreferrer"><span data-i18n="area.prod.acessar">Acessar</span><i data-lucide="arrow-up-right"></i></a>
<?php } elseif ($breve) { ?>
<span class="prod-status"><i data-lucide="clock"></i><span data-i18n="area.prod.breve.texto">Chega em breve</span></span>
<?php if (CREW_AREA_PLACAR) { ?><span class="prod-card__xpbreve"><i data-lucide="zap"></i>+<?= crew_area_num($p['xp']) ?> XP</span><?php } ?>
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
