<?php
/**
 * Crew - Início: os números das entregas e a lista do que espera o cliente na área do cliente.
 * Só abre para quem entrou pela área do cliente (a porta de login e o menu ficam em assets/php/area/layout.php).
 */
require __DIR__ . '/assets/php/auth.php';
require __DIR__ . '/assets/php/area/layout.php';
$perfil = crew_area_exigir_login();
crew_area_abrir('inicio', $perfil);
?>
<div class="crew-inicio" data-inicio aria-live="polite">
<section class="app-stats" aria-label="Resumo das entregas" data-i18n-attr="aria-label:area.inicio.resumo">
<a class="app-stat is-destaque" href="entregas.php" style="--dot: var(--st-voce-dot)">
<span class="app-stat__top"><span class="app-stat__dot"></span><span data-i18n="area.inicio.label">Esperando você</span></span>
<p class="app-stat__num" data-inicio-num="voce">&ndash;</p>
<p class="app-stat__sub" data-inicio-texto data-i18n="area.carregando">Carregando&hellip;</p>
</a>
<div class="app-stat" style="--dot: var(--st-ajuste-dot)">
<span class="app-stat__top"><span class="app-stat__dot"></span><span data-i18n="ent.filtro.ajuste">Ajuste pedido</span></span>
<p class="app-stat__num" data-inicio-num="ajuste">&ndash;</p>
<p class="app-stat__sub" data-i18n="area.inicio.ajuste">A Crew está ajustando</p>
</div>
<div class="app-stat" style="--dot: var(--st-prod-dot)">
<span class="app-stat__top"><span class="app-stat__dot"></span><span data-i18n="ent.filtro.producao">Em produção</span></span>
<p class="app-stat__num" data-inicio-num="producao">&ndash;</p>
<p class="app-stat__sub" data-i18n="area.inicio.producao">Sendo feitas pela equipe</p>
</div>
<div class="app-stat" style="--dot: var(--st-ok-dot)">
<span class="app-stat__top"><span class="app-stat__dot"></span><span data-i18n="ent.filtro.entregue">Entregues</span></span>
<p class="app-stat__num" data-inicio-num="entregue">&ndash;</p>
<p class="app-stat__sub" data-i18n="area.inicio.entregue">Prontas e aprovadas</p>
</div>
</section>
<?php if (CREW_AREA_PLACAR) { $xp = crew_area_xp(crew_area_produtos()); $nivel = crew_area_nivel($xp); ?>
<a class="xp-faixa" href="plataformas.php#pontos" data-nivel="<?= $nivel['atual'][0] ?>">
<img class="xp-painel__selo" src="assets/img/niveis/<?= $nivel['atual'][0] ?>.png" alt="" width="48" height="48">
<span class="xp-faixa__info">
<span class="xp-faixa__titulo"><span data-i18n="area.xp.nivel">Nível</span> <span data-i18n="area.nivel.<?= $nivel['atual'][0] ?>"><?= $nivel['atual'][1] ?></span> · <?= crew_area_num($xp) ?> XP</span>
<span class="xp-faixa__texto" data-i18n="area.xp.faixa">Quanto mais produtos da Crew você tem, mais pontos e mais benefícios. Veja os níveis.</span>
</span>
<span class="app-nivel__barra xp-faixa__barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $nivel['pct'] ?>"><span style="width: <?= $nivel['pct'] ?>%"></span></span>
<i data-lucide="arrow-right"></i>
</a>
<?php } else { ?>
<a class="xp-faixa" href="plataformas.php#pontos" data-nivel>
<i data-lucide="award"></i>
<span class="xp-faixa__info">
<span class="xp-faixa__titulo" data-i18n="area.xp.niveis">Pontos e níveis</span>
<span class="xp-faixa__texto"><span data-i18n="area.xp.embreve">Seus pontos começam a contar em breve.</span> <span data-i18n="area.xp.faixa.ver">Veja como funciona.</span></span>
</span>
<i data-lucide="arrow-right"></i>
</a>
<?php } ?>
<section class="app-panel">
<div class="app-panel__head">
<h2 class="app-panel__title" data-i18n="area.inicio.lista">Precisa da sua aprovação</h2>
<a class="app-panel__link" href="entregas.php"><span data-i18n="area.inicio.ver">Ver entregas</span><i data-lucide="arrow-right"></i></a>
</div>
<ul class="app-lista" data-inicio-lista></ul>
<p class="app-panel__vazio" data-inicio-vazio hidden></p>
<div class="app-panel__acao" data-inicio-tentar-box hidden><button type="button" class="crew-area-btn crew-area-btn--ghost" data-inicio-tentar><span data-i18n="area.tentar">Tentar de novo</span></button></div>
</section>
</div>
<?php crew_area_fechar([], ['assets/js/crew-area.js']); ?>
