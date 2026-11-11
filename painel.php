<?php
/**
 * Crew - Início: os números das entregas, os pontos e níveis da empresa (#pontos) e a lista do que espera o cliente.
 * Os pontos vieram de Plataformas para cá (Rafael, 2026-11-11); o placar e os benefícios seguem desligados na etapa 1.
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
<?php if (CREW_AREA_PLACAR) { $xp = crew_area_xp(crew_area_produtos()); $nivel = crew_area_nivel($xp); } ?>
<section class="xp-painel" id="pontos" data-nivel="<?= CREW_AREA_PLACAR ? $nivel['atual'][0] : '' ?>" aria-labelledby="xp-titulo">
<?php if (CREW_AREA_PLACAR) { ?>
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
<?php } else { ?>
<div class="xp-painel__resumo">
<span class="xp-painel__icone"><i data-lucide="award"></i></span>
<div class="xp-painel__info">
<span class="xp-painel__kicker" data-i18n="area.xp.seus">Seus pontos de experiência</span>
<h2 class="xp-painel__titulo" id="xp-titulo" data-i18n="area.xp.embreve">Seus pontos começam a contar em breve.</h2>
</div>
</div>
<?php } ?>
<div class="xp-painel__como">
<h3 class="xp-painel__sub" data-i18n="area.xp.como.titulo">Como funciona</h3>
<?php if (CREW_AREA_BENEFICIOS) { ?>
<p data-i18n="area.xp.como.texto">Cada produto da Crew que a sua empresa contrata soma pontos de experiência (XP). <strong>Quanto mais pontos, mais alto o seu nível e mais benefícios você tem.</strong> Os benefícios de um nível continuam valendo nos níveis seguintes.</p>
<?php } else { ?>
<p data-i18n="area.xp.como.pontos">A sua empresa soma pontos de experiência (XP) com os produtos da Crew que contrata e com a parceria no dia a dia, como responder as entregas no prazo. <strong>Quanto mais pontos, mais alto o seu nível.</strong></p>
<?php } ?>
</div>
<ol class="xp-niveis">
<?php foreach (crew_area_niveis() as $i => [$chave, $rotulo, $min, $beneficio]) {
    $estado = !CREW_AREA_PLACAR ? '' : ($i < $nivel['indice'] ? 'is-feito' : ($i === $nivel['indice'] ? 'is-atual' : 'is-bloqueado'));
?>
<li class="xp-nivel<?= $estado !== '' ? ' ' . $estado : '' ?>" data-nivel="<?= $chave ?>">
<span class="xp-nivel__icone"><img src="assets/img/niveis/<?= $chave ?>.png" alt="" width="56" height="56"><?php if ($estado === 'is-bloqueado') { ?><i data-lucide="lock"></i><?php } ?></span>
<span class="xp-nivel__nome" data-i18n="area.nivel.<?= $chave ?>"><?= $rotulo ?></span>
<?php if (CREW_AREA_PLACAR) { ?><span class="xp-nivel__min"><?= crew_area_num($min) ?> XP</span><?php } ?>
<?php if (CREW_AREA_BENEFICIOS) { ?><span class="xp-nivel__beneficio" data-i18n="area.nivel.<?= $chave ?>.beneficio"><?= $beneficio ?></span><?php } ?>
<?php if ($estado === 'is-atual') { ?><em class="xp-nivel__voce" data-i18n="area.xp.voce">Você está aqui</em><?php } ?>
</li>
<?php } ?>
</ol>
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
