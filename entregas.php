<?php
/**
 * Crew - Entregas: temas e artes para o cliente aprovar, por empresa (todas as frentes em que ela é cliente) da área do cliente.
 * Só abre para quem entrou pela área do cliente (a porta de login e o menu ficam em assets/php/area/layout.php).
 */
require __DIR__ . '/assets/php/auth.php';
require __DIR__ . '/assets/php/area/layout.php';
$perfil = crew_area_exigir_login();
crew_area_abrir('entregas', $perfil, ['assets/css/crew-entregas.css']);
?>
<section class="crew-entregas" data-entregas aria-live="polite">
<p class="crew-entregas__status" data-entregas-status data-i18n="area.carregando">Carregando&hellip;</p>
</section>
<?php crew_area_fechar([], ['assets/js/crew-area.js', 'assets/js/crew-entregas.js']); ?>
