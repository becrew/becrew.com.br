<?php
/**
 * Crew - formulário de contato (contato.html).
 * Redireciona de volta para contato.html?status=success|invalid|error (lido por main.js).
 */

$redirect = '../../contato.html';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

require __DIR__ . '/ratelimit.php';

// Limite de envios por IP: 3 por minuto (barra robôs que lotariam o e-mail de contato).
if (!crew_rl_hit('contact', 3, 60)) {
    header('Location: ' . $redirect . '?status=error');
    exit;
}

$recipient = 'contato@becrew.com.br';
$fromAddress = 'no-reply@becrew.com.br'; // Use um endereço do domínio do servidor (SPF/DKIM).

/* Lê um campo de texto: remove espaços extras e limita o tamanho. */
function crew_field($key, $max = 200) {
    $value = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
    return mb_substr($value, 0, $max, 'UTF-8');
}

/* Remove quebras de linha (evita injeção de cabeçalhos). */
function crew_oneline($value) {
    return trim(preg_replace('/[\r\n\t]+/', ' ', $value));
}

$nome = crew_oneline(crew_field('nome', 120));
$email = crew_oneline(crew_field('email', 160));
$empresa = crew_oneline(crew_field('empresa', 120));
$telefone = crew_oneline(crew_field('telefone', 40));
$investimento = crew_field('investimento', 20);
$mensagem = crew_field('mensagem', 5000);

$servicosPermitidos = [
    'Estratégia & Branding',
    'Conteúdo & Social Media',
    'Mídia & Performance',
    'Dados & CRM',
    'Sites & Landing Pages',
    'Comunidades',
    'Ainda não sei',
];
$servicos = isset($_POST['servicos']) && is_array($_POST['servicos'])
    ? array_values(array_intersect($servicosPermitidos, $_POST['servicos']))
    : [];

$faixas = [
    'ate-5k' => 'Até R$ 5 mil',
    '5k-15k' => 'R$ 5–15 mil',
    '15k-50k' => 'R$ 15–50 mil',
    'acima-50k' => 'Acima de R$ 50 mil',
];

if ($nome === '' || $empresa === '' || $mensagem === ''
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !isset($faixas[$investimento])) {
    header('Location: ' . $redirect . '?status=invalid');
    exit;
}

$subject = 'Novo contato pelo site: ' . $empresa;
$body = "Novo contato recebido pelo site da Crew.\n\n"
    . "Nome: {$nome}\n"
    . "E-mail: {$email}\n"
    . "Empresa: {$empresa}\n"
    . 'Telefone / WhatsApp: ' . ($telefone !== '' ? $telefone : 'não informado') . "\n"
    . 'Serviços de interesse: ' . ($servicos ? implode(', ', $servicos) : 'não informado') . "\n"
    . 'Investimento mensal estimado: ' . $faixas[$investimento] . "\n\n"
    . "Mensagem:\n{$mensagem}\n";

$headers = [
    'From: ' . mb_encode_mimeheader('Site Crew', 'UTF-8') . ' <' . $fromAddress . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$sent = @mail(
    $recipient,
    mb_encode_mimeheader($subject, 'UTF-8'),
    $body,
    implode("\r\n", $headers)
);

header('Location: ' . $redirect . '?status=' . ($sent ? 'success' : 'error'));
exit;
