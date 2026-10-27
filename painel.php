<?php
/**
 * Crew - Início: quantas entregas esperam o cliente da área do cliente.
 * Só abre para quem entrou pela área do cliente (a porta de login e o menu ficam em assets/php/area/layout.php).
 */
require __DIR__ . '/assets/php/auth.php';
require __DIR__ . '/assets/php/area/layout.php';
$perfil = crew_area_exigir_login();
crew_area_abrir('inicio', $perfil);
?>
<section class="crew-area-card crew-inicio" data-inicio aria-live="polite">
<span class="crew-area-card__label" data-i18n="area.inicio.label">Esperando você</span>
<p class="crew-inicio__num" data-inicio-num>&ndash;</p>
<p class="crew-inicio__text" data-inicio-texto data-i18n="area.carregando">Carregando&hellip;</p>
<button type="button" class="crew-area-btn crew-area-btn--ghost" data-inicio-tentar hidden><span data-i18n="area.tentar">Tentar de novo</span></button>
<a class="crew-area-btn" href="entregas.php"><span data-i18n="area.inicio.ver">Ver entregas</span><i data-lucide="arrow-right"></i></a>
</section>
<?php crew_area_fechar([], ['assets/js/crew-area.js']); ?>
