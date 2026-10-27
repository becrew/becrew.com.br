<?php
/**
 * Crew - sessão da área do cliente (usado por login.php, logout.php, pelas páginas da área e pela ponte).
 */
if (!defined('CREW_AUTH')) define('CREW_AUTH', true);

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

/* Código da empresa na Plataforma (EMP-0000). */
function crew_empresa_valida($v) {
    return is_string($v) && preg_match('/^EMP-\d{4,}$/D', $v) === 1;
}

/* Perfil a partir da linha do users.php: e-mail e empresa (null se não tiver ou vier fora do formato). */
function crew_profile_from($row, $email) {
    $empresa = is_array($row) && isset($row['empresa']) && crew_empresa_valida($row['empresa']) ? $row['empresa'] : null;
    return ['email' => $email, 'empresa' => $empresa];
}

/* Perfil do usuário logado (['email', 'empresa']) ou null se não houver login. */
function crew_current_profile() {
    $email = crew_current_user();
    return $email === null ? null : crew_profile_from(crew_users()[$email], $email);
}
