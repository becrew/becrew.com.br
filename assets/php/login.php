<?php
/**
 * Crew - login da área do cliente (area-do-cliente.html).
 * Responde em JSON para o formulário ({ok, redirect} ou {ok:false, error}).
 * Sem JavaScript, redireciona para o painel ou de volta ao login com ?erro=1.
 */
require __DIR__ . '/auth.php';
require __DIR__ . '/ratelimit.php';

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
$panel = '../../painel.php';
$login = '../../area-do-cliente.html';

function crew_reply($ok, $wantsJson, $target, $error = '') {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (!$ok) http_response_code($error === 'limit' ? 429 : 401);
        echo json_encode($ok ? ['ok' => true, 'redirect' => 'painel.php'] : ['ok' => false, 'error' => $error]);
    } else {
        header('Location: ' . $target, true, 303);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $login);
    exit;
}

crew_session_start();

// Limite por IP, gravado no servidor (apagar o cookie não adianta): 8 erros bloqueiam por 15 minutos.
if (crew_rl_blocked('login', 8, 900) > 0) {
    crew_reply(false, $wantsJson, $login . '?erro=limite', 'limit');
}

// Limite por sessão, como segunda camada: 5 erros bloqueiam por 5 minutos.
$now = time();
if (!empty($_SESSION['crew_lock_until']) && $_SESSION['crew_lock_until'] > $now) {
    crew_reply(false, $wantsJson, $login . '?erro=limite', 'limit');
}

$email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
$users = crew_users();
$hash = isset($users[$email]['hash']) ? $users[$email]['hash'] : null;

// Sempre roda password_verify (mesmo sem usuário) para não revelar quais e-mails existem pelo tempo de resposta.
$dummy = '$2y$11$oEpSBVxOkUCTZWm/ZxN2t.HBAoJbsjUCJj6KlkSUCIP1P0iYZCP2y';
$valid = password_verify($password, $hash ?: $dummy) && $hash !== null;

if (!$valid) {
    crew_rl_fail('login', 900);
    $_SESSION['crew_fails'] = (isset($_SESSION['crew_fails']) ? $_SESSION['crew_fails'] : 0) + 1;
    if ($_SESSION['crew_fails'] >= 5) {
        $_SESSION['crew_lock_until'] = $now + 300;
        $_SESSION['crew_fails'] = 0;
    }
    usleep(400000);
    crew_reply(false, $wantsJson, $login . '?erro=1', 'invalid');
}

crew_rl_reset('login');
session_regenerate_id(true);
$_SESSION['crew_user'] = $email;
$_SESSION['crew_fails'] = 0;
unset($_SESSION['crew_lock_until']);
crew_reply(true, $wantsJson, $panel);
