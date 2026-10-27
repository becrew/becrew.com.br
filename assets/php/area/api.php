<?php
/**
 * Crew - ponte entre a área do cliente e a Plataforma (única porta; o navegador nunca fala com a Plataforma).
 *   GET  api.php?acao=entregas  -> lista as entregas da empresa do login (todas as frentes em que ela é cliente)
 *   POST api.php?acao=decidir   -> o cliente aprova ou pede ajuste do tema ou da arte
 * A empresa vem sempre da sessão (users.php), nunca do navegador. O token fica fora do public_html.
 * Se a Plataforma falhar, o navegador recebe um aviso genérico e nada é gravado.
 */
require __DIR__ . '/../auth.php';
require __DIR__ . '/../ratelimit.php';
require __DIR__ . '/plataforma.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

function crew_area_responder(int $status, array $corpo): void {
    http_response_code($status);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Limite de uso: por usuário logado (hash do e-mail, nunca o e-mail no arquivo) e por IP (3x maior: um provedor pode ter vários logins no mesmo IP). */
function crew_area_limite(string $rota, int $porUsuario, string $email): void {
    $u = crew_rl_hit("area-$rota-user", $porUsuario, 60, 'u|' . strtolower($email));
    $i = crew_rl_hit("area-$rota-ip", $porUsuario * 3, 60);
    if (!$u || !$i) {
        header('Retry-After: 60');
        crew_area_responder(429, ['erro' => 'muitas requisições']);
    }
}

$perfil = crew_current_profile();
session_write_close(); // uma chamada lenta à Plataforma não pode travar as outras abas do cliente
if ($perfil === null) crew_area_responder(401, ['erro' => 'entre na área do cliente']);
if ($perfil['empresa'] === null) crew_area_responder(404, ['erro' => 'não encontrado']); // usuário sem empresa: sem entregas
$empresa = $perfil['empresa'];
$metodo = $_SERVER['REQUEST_METHOD'] ?? '';
$acao = isset($_GET['acao']) && is_string($_GET['acao']) ? $_GET['acao'] : '';

if ($acao === 'entregas') {
    if ($metodo !== 'GET') { header('Allow: GET'); crew_area_responder(405, ['erro' => 'método não permitido']); }
    crew_area_limite('ler', 60, $perfil['email']);
    [$status, $bruto] = crew_area_chamar('GET', '/api/site/v1/entregas?' . http_build_query(['empresa' => $empresa]));
    $corpo = $status === 200 ? json_decode($bruto, true) : null;
    if ($status === 200 && is_array($corpo)) {
        echo json_encode(crew_area_anotar($corpo), JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Log só com status e código da empresa, nunca nome, e-mail nem conteúdo.
    error_log("crew area: entregas, plataforma respondeu $status para $empresa");
    if ($status === 404) crew_area_responder(404, ['erro' => 'não encontrado']);
    crew_area_responder(502, ['erro' => 'indisponível']);
}

if ($acao === 'decidir') {
    if ($metodo !== 'POST') { header('Allow: POST'); crew_area_responder(405, ['erro' => 'método não permitido']); }
    crew_area_limite('dec', 20, $perfil['email']);

    // Escrita: cabeçalho que um formulário de outro site não consegue mandar e Origin com o mesmo host (e porta) do site.
    $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
    $p = parse_url($origem);
    $hostOrigem = isset($p['host']) ? strtolower($p['host']) . (isset($p['port']) ? ':' . $p['port'] : '') : '';
    $hostSite = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch' || $hostOrigem === '' || $hostOrigem !== $hostSite) {
        crew_area_responder(403, ['erro' => 'requisição recusada']);
    }

    $bruto = (string) file_get_contents('php://input', false, null, 0, 16001);
    if (strlen($bruto) > 16000) crew_area_responder(413, ['erro' => 'corpo grande demais']);
    $decisao = crew_area_montar_decisao(json_decode($bruto, true), $empresa, $perfil['email'], gmdate('c'));
    if ($decisao === null) crew_area_responder(400, ['erro' => 'corpo inválido']);

    [$status, $resp] = crew_area_chamar('POST', '/api/site/v1/decisoes', $decisao);
    $corpo = in_array($status, [200, 409], true) ? json_decode($resp, true) : null;
    if ($status === 200 && is_array($corpo) && is_array($corpo['entrega'] ?? null)) {
        $corpo['entrega']['acao_cliente'] = crew_area_acao_cliente($corpo['entrega']);
        crew_area_responder(200, ['repetida' => !empty($corpo['repetida']), 'entrega' => $corpo['entrega']]);
    }
    if ($status === 409 && is_array($corpo)) {
        // 409 traz codigo e mensagem para a tela mostrar e recarregar a lista.
        crew_area_responder(409, array_intersect_key($corpo, ['erro' => 1, 'codigo' => 1, 'mensagem' => 1]));
    }
    error_log("crew area: decisao, plataforma respondeu $status para $empresa");
    if ($status === 404) crew_area_responder(404, ['erro' => 'não encontrado']);
    if ($status === 400) crew_area_responder(400, ['erro' => 'corpo inválido']);
    crew_area_responder(502, ['erro' => 'indisponível']);
}

crew_area_responder(404, ['erro' => 'não encontrado']);
