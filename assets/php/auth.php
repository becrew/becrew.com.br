<?php
/**
 * Crew - sessão da área do cliente (usado por login.php, logout.php e painel.php).
 */
define('CREW_AUTH', true);

function crew_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('crew_cliente');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function crew_users() {
    static $users = null;
    if ($users === null) $users = require __DIR__ . '/users.php';
    return $users;
}

function crew_current_user() {
    crew_session_start();
    return isset($_SESSION['crew_user']) && isset(crew_users()[$_SESSION['crew_user']]) ? $_SESSION['crew_user'] : null;
}
