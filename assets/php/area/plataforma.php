<?php
/**
 * Crew - cliente da API da Plataforma (usado só pela ponte, api.php).
 * Contrato: a Plataforma publica GET /api/site/v1/entregas e POST /api/site/v1/decisoes.
 * O token fica num arquivo fora do public_html (crew-plataforma.php) e nunca sai daqui.
 */
if (!defined('CREW_AUTH')) { http_response_code(404); exit; }
require_once __DIR__ . '/../auth.php';

/**
 * Lê o arquivo do token: ['url' => 'https://...', 'token' => 'crewsite_...'].
 * Devolve null se faltar, estiver quebrado ou fora do formato (a ponte responde 502 genérico).
 * $permitirLocal só é verdadeiro em teste local: aceita http:// em 127.0.0.1 ou localhost.
 */
function crew_area_ler_config(string $arquivo, bool $permitirLocal = false): ?array {
    if (!is_file($arquivo)) return null;
    // Trocar o token (rotação) vale na hora: sem isso o opcache pode servir a versão antiga por alguns segundos.
    if (function_exists('opcache_invalidate')) @opcache_invalidate($arquivo, true);
    try {
        $c = (static function ($f) { return require $f; })($arquivo);
    } catch (\Throwable $e) {
        return null;
    }
    if (!is_array($c) || !isset($c['url'], $c['token']) || !is_string($c['url']) || !is_string($c['token'])) return null;
    $p = parse_url($c['url']);
    $local = $permitirLocal && ($p['scheme'] ?? '') === 'http' && in_array($p['host'] ?? '', ['127.0.0.1', 'localhost'], true);
    if (!$local && ($p['scheme'] ?? '') !== 'https') return null;
    if (empty($p['host'])) return null;
    if (preg_match('/^crewsite_[A-Za-z0-9_-]{6,}$/D', $c['token']) !== 1) return null;
    return ['url' => rtrim($c['url'], '/'), 'token' => $c['token']];
}

/* O arquivo do token fica um nível acima do public_html (a partir de assets/php/area). */
function crew_area_config(): ?array {
    $teste = getenv('CREW_PLATAFORMA_CONFIG');
    if (is_string($teste) && $teste !== '') return crew_area_ler_config($teste, true);
    return crew_area_ler_config(dirname(__DIR__, 4) . '/crew-plataforma.php');
}

/**
 * Chama a Plataforma. Devolve [status, corpo bruto]; status 0 = não deu para falar com ela.
 * O motivo da falha (e o token) nunca vão ao navegador.
 */
function crew_area_chamar(string $metodo, string $caminho, ?array $json = null): array {
    $cfg = crew_area_config();
    if ($cfg === null) {
        error_log('crew area: arquivo do token da Plataforma ausente ou inválido');
        return [0, ''];
    }
    if (!function_exists('curl_init')) {
        error_log('crew area: extensão cURL indisponível');
        return [0, ''];
    }
    $cab = ['Authorization: Bearer ' . $cfg['token'], 'Accept: application/json'];
    $ch = curl_init($cfg['url'] . $caminho);
    $op = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($metodo === 'POST') {
        $cab[] = 'Content-Type: application/json';
        $op[CURLOPT_POST] = true;
        $op[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE);
    }
    $op[CURLOPT_HTTPHEADER] = $cab;
    curl_setopt_array($ch, $op);
    $corpo = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return $corpo === false ? [0, ''] : [$status, (string) $corpo];
}

/**
 * O que a entrega espera do cliente: 'tema', 'arte' ou null (tabela do desenho de Entregas).
 * O tema vem primeiro: a arte só se decide com o tema aprovado (ou sem tema).
 */
function crew_area_acao_cliente(array $e): ?string {
    $coluna = $e['coluna'] ?? '';
    $tema = $e['tema'] ?? null;
    $estado = $e['tema_estado'] ?? '';
    if ($tema !== null && $estado === 'pendente' && $coluna !== 'entregue') return 'tema';
    if ($coluna === 'com_cliente' && is_array($e['versao'] ?? null) && ($tema === null || $estado === 'aprovado')) return 'arte';
    return null;
}

/* Acrescenta acao_cliente em cada entrega e o total que espera o cliente. O resto do corpo fica como veio. */
function crew_area_anotar(array $corpo): array {
    $lista = is_array($corpo['entregas'] ?? null) ? $corpo['entregas'] : [];
    $esperando = 0;
    foreach ($lista as $i => $e) {
        if (!is_array($e)) { unset($lista[$i]); continue; }
        $acao = crew_area_acao_cliente($e);
        $lista[$i]['acao_cliente'] = $acao;
        if ($acao !== null) $esperando++;
    }
    $corpo['entregas'] = array_values($lista);
    $corpo['esperando'] = $esperando;
    return $corpo;
}

/**
 * Transforma o que o navegador mandou no corpo do POST /decisoes.
 * Só os campos conhecidos passam; empresa, por e em vêm da sessão e do servidor, nunca do navegador.
 * Devolve null se a decisão não for válida.
 */
function crew_area_montar_decisao($entrada, string $empresa, string $email, string $agora): ?array {
    if (!is_array($entrada) || !crew_empresa_valida($empresa)) return null;
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
    $tarefa = $entrada['tarefa'] ?? null;
    if (!is_string($tarefa) || preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D', $tarefa) !== 1) return null;
    $tipo = $entrada['tipo'] ?? null;
    if (!in_array($tipo, ['tema', 'arte'], true)) return null;
    $decisao = $entrada['decisao'] ?? null;
    if (!in_array($decisao, ['aprovada', 'ajuste'], true)) return null;
    $v = $entrada['versao'] ?? null;
    if (is_string($v) && preg_match('/^\d{1,9}$/D', $v) === 1) $v = (int) $v;
    if (!is_int($v) || $v < 1) return null;

    $motivos = [];
    if (is_array($entrada['motivos'] ?? null)) {
        foreach ($entrada['motivos'] as $m) {
            if (!is_scalar($m)) continue;
            $m = mb_substr(trim((string) $m), 0, 80);
            if ($m !== '') $motivos[] = $m;
            if (count($motivos) === 8) break;
        }
    }
    $sugestao = is_string($entrada['sugestao'] ?? null) ? mb_substr(trim($entrada['sugestao']), 0, 1000) : '';
    if ($decisao === 'ajuste' && !$motivos && $sugestao === '') return null;

    return [
        'empresa' => $empresa, 'tarefa' => $tarefa, 'tipo' => $tipo, 'versao' => $v, 'decisao' => $decisao,
        'motivos' => $motivos, 'sugestao' => $sugestao, 'por' => $email, 'em' => $agora,
    ];
}
