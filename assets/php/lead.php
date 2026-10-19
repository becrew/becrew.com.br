<?php
/**
 * Crew - recebe os leads da landing page do Raio X da Operação para provedores
 * (/marketing-para-provedores-de-internet/). Recebe POST em JSON e responde {"ok": true|false}.
 *
 * - Envia o lead por e-mail para contato@becrew.com.br.
 * - Se $webhook estiver preenchido, repassa o mesmo JSON para ele (Make, n8n, Apps Script, CRM).
 *   É aqui também que a API de Conversões da Meta pode ser chamada (o campo event_id deduplica com o Pixel).
 */

$recipient   = 'contato@becrew.com.br';
$fromAddress = 'no-reply@becrew.com.br';
$webhook     = ''; // PREENCHER (opcional): URL do webhook que também deve receber os leads

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function crew_lead_reply($ok, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') crew_lead_reply(false, 405);

$raw = file_get_contents('php://input', false, null, 0, 20000);
$data = json_decode($raw, true);
if (!is_array($data)) crew_lead_reply(false, 400);

/* Texto de uma linha, sem quebras (evita injeção de cabeçalho), com limite de tamanho. */
function crew_lead_field($data, $key, $max = 200) {
    $v = isset($data[$key]) && is_scalar($data[$key]) ? trim((string) $data[$key]) : '';
    $v = preg_replace('/[\r\n\t]+/', ' ', $v);
    return mb_substr($v, 0, $max, 'UTF-8');
}

$lead = [];
foreach (['nome', 'whatsapp', 'provedor', 'cidade', 'assinantes', 'cargo', 'email', 'lead_score', 'origem',
          'form_position', 'pagina', 'data_hora', 'event_id', 'utm_source', 'utm_medium', 'utm_campaign',
          'utm_content', 'utm_term', 'gclid', 'fbclid'] as $k) {
    $lead[$k] = crew_lead_field($data, $k, $k === 'pagina' ? 400 : 200);
}
$lead['consentimento'] = !empty($data['consentimento']);

/* Validação mínima (a página já valida antes de enviar). */
$digits = preg_replace('/\D/', '', $lead['whatsapp']);
if ($lead['nome'] === '' || $lead['provedor'] === '' || $lead['cidade'] === '' || !$lead['consentimento']
    || (strlen($digits) !== 10 && strlen($digits) !== 11)
    || ($lead['email'] !== '' && !filter_var($lead['email'], FILTER_VALIDATE_EMAIL))
    || !in_array($lead['lead_score'], ['quente', 'morno', 'frio'], true)) {
    crew_lead_reply(false, 422);
}
if (!empty($data['website'])) crew_lead_reply(true); // honeypot: finge sucesso para o robô

/* E-mail */
$labels = [
    'nome' => 'Nome', 'whatsapp' => 'WhatsApp', 'provedor' => 'Provedor', 'cidade' => 'Cidade principal',
    'assinantes' => 'Assinantes', 'cargo' => 'Cargo', 'email' => 'E-mail', 'lead_score' => 'Lead score',
    'origem' => 'Origem (anúncio)', 'form_position' => 'Formulário', 'pagina' => 'Página', 'data_hora' => 'Data e hora',
    'utm_source' => 'utm_source', 'utm_medium' => 'utm_medium', 'utm_campaign' => 'utm_campaign',
    'utm_content' => 'utm_content', 'utm_term' => 'utm_term', 'gclid' => 'gclid', 'fbclid' => 'fbclid', 'event_id' => 'event_id',
];
$lines = [];
foreach ($labels as $k => $label) {
    if ($lead[$k] !== '') $lines[] = $label . ': ' . $lead[$k];
}
$lines[] = 'Consentimento LGPD: sim';
$lines[] = '';
$lines[] = 'Chamar no WhatsApp: https://wa.me/55' . ltrim($digits, '0');

$score = strtoupper($lead['lead_score']);
$subject = "[Raio X {$score}] {$lead['provedor']} - {$lead['cidade']}";
$headers = [
    'From: Crew <' . $fromAddress . '>',
    'Content-Type: text/plain; charset=UTF-8',
];
if ($lead['email'] !== '') $headers[] = 'Reply-To: ' . $lead['email'];

$mailed = mail($recipient, '=?UTF-8?B?' . base64_encode($subject) . '?=', implode("\n", $lines), implode("\r\n", $headers));

/* Webhook (opcional) */
$forwarded = false;
if ($webhook !== '' && function_exists('curl_init')) {
    $ch = curl_init($webhook);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($lead),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    curl_exec($ch);
    $forwarded = curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400;
    curl_close($ch);
}

crew_lead_reply($mailed || $forwarded, ($mailed || $forwarded) ? 200 : 502);
