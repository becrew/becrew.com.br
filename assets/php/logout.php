<?php
/**
 * Crew - sai da área do cliente e volta para o login.
 */
require __DIR__ . '/auth.php';
crew_session_start();
$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();
header('Location: ../../area-do-cliente.html', true, 303);
exit;
