<?php
/**
 * Crew - Plataformas: os cartões das plataformas (Speakr, Gaming, Ruller). Abre também para usuário sem empresa da área do cliente.
 * Só abre para quem entrou pela área do cliente (a porta de login e o menu ficam em assets/php/area/layout.php).
 */
require __DIR__ . '/assets/php/auth.php';
require __DIR__ . '/assets/php/area/layout.php';
$perfil = crew_area_exigir_login(false);
crew_area_abrir('plataformas', $perfil);
?>
<section class="crew-deck" aria-roledescription="carousel" aria-label="Plataformas" data-i18n-attr="aria-label:painel.deck">
<div class="crew-deck__stage" tabindex="0">
<article class="crew-deck__card is-themed" style="--deck-accent: 255 212 0; --deck-deep: 36 30 4; --deck-on: 28 22 0" data-index="0" aria-roledescription="slide" aria-label="01 / 03">
<div class="crew-deck__top">
<span class="crew-deck__icon"><i data-lucide="bar-chart"></i></span>
<span class="crew-deck__num">01</span>
</div>
<h2 class="crew-deck__title" data-i18n="painel.speakr.title">SPEAKR.CREW</h2>
<div class="crew-deck__media"><img alt="" src="assets/img/equipe-resultados.webp" draggable="false" loading="eager"></div>
<p class="crew-deck__text" data-i18n="painel.speakr.text">Entre no <strong>Speakr</strong> e acompanhe os relatórios, as análises e as visões estratégicas do seu negócio.</p>
<button type="button" class="crew-deck__cta is-soon" data-soon aria-describedby="alert-speakr"><span data-i18n="painel.access">Acessar</span><i data-lucide="arrow-up-right"></i></button>
<div class="crew-deck__alert" id="alert-speakr" role="alert" hidden>
<span class="crew-deck__alert-icon"><i data-lucide="lock"></i></span>
<p data-i18n="painel.alert">Seu usuário não tem essa plataforma disponível. Quer saber mais? <a href="contato.html">Entre em contato com a nossa equipe.</a></p>
<button type="button" class="crew-deck__alert-close" aria-label="Fechar aviso" data-i18n-attr="aria-label:painel.alert.close"><i data-lucide="x"></i></button>
</div>
</article>
<article class="crew-deck__card is-themed is-ink-white" style="--deck-accent: 240 52 52; --deck-deep: 34 8 8; --deck-on: 24 4 4" data-index="1" aria-roledescription="slide" aria-label="02 / 03">
<div class="crew-deck__top">
<span class="crew-deck__icon"><i data-lucide="gamepad-2"></i></span>
<span class="crew-deck__num">02</span>
</div>
<h2 class="crew-deck__title" data-i18n="painel.gaming.title">GAMING.CREW</h2>
<div class="crew-deck__media"><img alt="" src="assets/img/painel-gaming.webp" draggable="false" loading="eager"></div>
<p class="crew-deck__text" data-i18n="painel.gaming.text">Entre no <strong>Side Quest</strong> e acompanhe as missões, a comunidade gamer e os resultados das ações da sua marca.</p>
<a class="crew-deck__cta" href="https://side-quest-drab-two.vercel.app/dashboard" target="_blank" rel="noopener noreferrer"><span data-i18n="painel.open">Acessar</span><i data-lucide="arrow-up-right"></i></a>
</article>
<article class="crew-deck__card is-themed" style="--deck-accent: 136 248 72; --deck-deep: 9 30 12; --deck-on: 6 20 10" data-index="2" aria-roledescription="slide" aria-label="03 / 03">
<div class="crew-deck__top">
<span class="crew-deck__icon"><i data-lucide="target"></i></span>
<span class="crew-deck__num">03</span>
</div>
<h2 class="crew-deck__title" data-i18n="painel.ruller.title">RULLER.CREW</h2>
<div class="crew-deck__media"><img alt="" src="assets/img/painel-ruller.webp" draggable="false" loading="eager"></div>
<p class="crew-deck__text" data-i18n="painel.ruller.text">Entre no <strong>Ruller</strong> e acompanhe o planejamento, as entregas e os indicadores das suas campanhas.</p>
<button type="button" class="crew-deck__cta is-soon" data-soon aria-describedby="alert-ruller"><span data-i18n="painel.access">Acessar</span><i data-lucide="arrow-up-right"></i></button>
<div class="crew-deck__alert" id="alert-ruller" role="alert" hidden>
<span class="crew-deck__alert-icon"><i data-lucide="lock"></i></span>
<p data-i18n="painel.alert">Seu usuário não tem essa plataforma disponível. Quer saber mais? <a href="contato.html">Entre em contato com a nossa equipe.</a></p>
<button type="button" class="crew-deck__alert-close" aria-label="Fechar aviso" data-i18n-attr="aria-label:painel.alert.close"><i data-lucide="x"></i></button>
</div>
</article>
</div>
<div class="crew-deck__controls">
<button type="button" class="crew-deck__arrow" data-dir="-1" aria-label="Anterior" data-i18n-attr="aria-label:painel.prev"><i data-lucide="arrow-left"></i></button>
<span class="crew-deck__counter" aria-live="polite"><strong data-deck-current>01</strong> / 03</span>
<button type="button" class="crew-deck__arrow" data-dir="1" aria-label="Próxima" data-i18n-attr="aria-label:painel.next"><i data-lucide="arrow-right"></i></button>
</div>
<p class="crew-deck__hint" data-i18n="painel.hint">Arraste o cartão para o lado ou use as setas.</p>
</section>
<nav class="crew-deck__chips" aria-label="Todas as plataformas" data-i18n-attr="aria-label:painel.all">
<button type="button" class="crew-deck__chip is-themed" style="--deck-accent: 255 212 0; --deck-on: 28 22 0" data-go="0"><i data-lucide="bar-chart"></i><span data-i18n="painel.speakr.title">SPEAKR.CREW</span></button>
<button type="button" class="crew-deck__chip is-themed" style="--deck-accent: 240 52 52; --deck-on: 24 4 4" data-go="1"><i data-lucide="gamepad-2"></i><span data-i18n="painel.gaming.title">GAMING.CREW</span></button>
<button type="button" class="crew-deck__chip is-themed" style="--deck-accent: 136 248 72; --deck-on: 6 20 10" data-go="2"><i data-lucide="target"></i><span data-i18n="painel.ruller.title">RULLER.CREW</span></button>
</nav>
<?php crew_area_fechar(['painel'], ['assets/js/crew-painel.js']); ?>
