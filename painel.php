<?php
/**
 * Crew - Início: os números das entregas e a lista do que espera o cliente na área do cliente.
 * Os pontos ficam só no menu, com página própria (pontos.php; Rafael, 2026-11-11).
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
