<?php
/**
 * Crew - porta do painel de plataformas.
 * Só mostra painel-cliente.html para quem entrou pela área do cliente;
 * o acesso direto ao arquivo .html é bloqueado no .htaccess.
 */
require __DIR__ . '/assets/php/auth.php';

if (!crew_current_user()) {
    header('Location: area-do-cliente.html', true, 303);
    exit;
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
readfile(__DIR__ . '/painel-cliente.html');
