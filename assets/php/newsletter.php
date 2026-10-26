<?php
/**
 * Crew - inscrição na newsletter (formulário do rodapé e da lateral do blog).
 *
 * Envia o e-mail do inscrito para contato@becrew.com.br.
 * - Com JavaScript (fetch, cabeçalho Accept: application/json): responde JSON {status}.
 * - Sem JavaScript: redireciona de volta para a página de origem com ?newsletter=ok|invalid|error.
 */

$recipient   = 'contato@becrew.com.br';
$fromAddress = 'no-reply@becrew.com.br'; // Use um endereço do domínio do servidor (SPF/DKIM).

$pages = ['index.html', 'sobre.html', 'servicos.html', 'comunidades.html', 'cases.html',
          'case.html', 'blog.html', 'contato.html'];

/* Aceita também os posts do blog (blog/<slug>.html). */
function crew_valid_page($page, $pages) {
    return in_array($page, $pages, true) || preg_match('#^blog/[a-z0-9-]+\.html$#', $page) === 1;
}

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function crew_newsletter_done($status, $wantsJson, $pages) {
    if ($wantsJson) {
        if ($status === 'limit') {
            http_response_code(429);
            $status = 'error';
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => $status]);
        exit;
    }
    if ($status === 'limit') $status = 'error';
    $origin = isset($_POST['origem']) && is_string($_POST['origem']) ? $_POST['origem'] : '';
    $page = crew_valid_page($origin, $pages) ? $origin : 'index.html';
    header('Location: ../../' . $page . '?newsletter=' . $status . '#newsletter');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    crew_newsletter_done('invalid', $wantsJson, $pages);
}

// Limite de envios por IP: 5 por minuto.
require __DIR__ . '/ratelimit.php';
if (!crew_rl_hit('newsletter', 5, 60)) {
    crew_newsletter_done('limit', $wantsJson, $pages);
}

// Honeypot: bots costumam preencher todos os campos.
if (!empty($_POST['website'])) {
    crew_newsletter_done('ok', $wantsJson, $pages);
}

$email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
$email = preg_replace('/[\r\n\t]+/', '', mb_substr($email, 0, 160, 'UTF-8'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    crew_newsletter_done('invalid', $wantsJson, $pages);
}

$origin = isset($_POST['origem']) && is_string($_POST['origem']) && crew_valid_page($_POST['origem'], $pages)
    ? $_POST['origem'] : 'não informada';
$lang = (isset($_POST['idioma']) && $_POST['idioma'] === 'en') ? 'Inglês' : 'Português';

$subject = '=?UTF-8?B?' . base64_encode('Nova inscrição na newsletter: ' . $email) . '?=';
$body = "Nova inscrição na newsletter do site da Crew.\n\n"
      . "E-mail: {$email}\n"
      . "Página: {$origin}\n"
      . "Idioma do site: {$lang}\n"
      . 'Data: ' . date('d/m/Y H:i') . "\n";

$headers = [
    'From: Site Crew <' . $fromAddress . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$sent = @mail($recipient, $subject, $body, implode("\r\n", $headers));
crew_newsletter_done($sent ? 'ok' : 'error', $wantsJson, $pages);
