<?php
/**
 * Crew - Plataformas: o que o cliente acessa com o login da própria plataforma. Hoje só o Side Quest
 * (Rafael, 2026-11-11: "em plataformas deve ficar apenas a side quest").
 * O botão "Entrar no Side Quest" aparece para todos, sem o selo "Contratado": o acesso é pelo login do Side Quest.
 * A lista fica em assets/php/area/produtos.php (crew_area_plataformas). Abre também para usuário sem empresa.
 * Só abre para quem entrou pela área do cliente (a porta de login e o menu ficam em assets/php/area/layout.php).
 */
require __DIR__ . '/assets/php/auth.php';
require __DIR__ . '/assets/php/area/layout.php';
$perfil = crew_area_exigir_login(false);

crew_area_abrir('plataformas', $perfil, ['assets/css/crew-produtos.css']);
?>
<section class="prod-grade" aria-label="Plataformas" data-i18n-attr="aria-label:area.nav.plataformas">
<?php foreach (crew_area_plataformas() as $p) { ?>
<article class="prod-card is-acesso" data-cat="games" style="--prod-cor: <?= $p['cor'] ?>">
<div class="prod-card__foto">
<img alt="" src="<?= $p['foto'] ?>" loading="lazy">
<span class="prod-card__tipo"><i data-lucide="<?= $p['tipo'][2] ?>"></i><span data-i18n="<?= $p['tipo'][0] ?>"><?= $p['tipo'][1] ?></span></span>
</div>
<div class="prod-card__corpo">
<span class="prod-card__cat" data-i18n="area.prod.cat.games">Games</span>
<h2 class="prod-card__nome" data-i18n="painel.<?= $p['chave'] ?>.title"><?= $p['nome'] ?></h2>
<p class="prod-card__texto" data-i18n="painel.<?= $p['chave'] ?>.text"><?= $p['texto'] ?></p>
<div class="prod-card__pe">
<span class="prod-status"><i data-lucide="user"></i><span data-i18n="area.plat.login">O acesso é com o seu login do Side Quest.</span></span>
<a class="crew-area-btn" href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener noreferrer"><span data-i18n="area.plat.entrar">Entrar no Side Quest</span><i data-lucide="arrow-up-right"></i></a>
</div>
</div>
</article>
<?php } ?>
</section>
<?php crew_area_fechar(); ?>
