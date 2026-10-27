<?php
/**
 * Plataforma de mentira, só para os testes e para a conferência local da área do cliente.
 * Imita o contrato do site (GET /api/site/v1/entregas e POST /api/site/v1/decisoes):
 * token, empresa, rodadas, idempotência e os códigos 404/409. Não tem dado real.
 *
 * Rodar: MOCK_DIR=/pasta/temporaria php -S 127.0.0.1:8799 tests/mock-plataforma.php
 * Gravar em MOCK_DIR: estado (state.json) e o registro do que chegou (log.jsonl).
 * Truques de teste: POST /__test/avancar, POST /__test/reset, arquivo MOCK_DIR/falha com um código HTTP.
 */
const TOKEN = 'crewsite_testtoken0123456789';
$dir = getenv('MOCK_DIR') ?: sys_get_temp_dir() . '/mock-plataforma';
@mkdir($dir, 0700, true);
$stateFile = $dir . '/state.json';
$logFile = $dir . '/log.jsonl';

function semente(): array {
    $link = fn($n) => 'https://arquivos.example.test/a/' . $n . '?t=assinado';
    return [
        'empresas' => [
            'EMP-0001' => [
                'frentes' => [['chave' => 'agencia', 'nome' => 'Agência Crew'], ['chave' => 'gaming', 'nome' => 'Crew Gaming']],
                'entregas' => [
                    ['id' => 'tarefa-a1', 'frente' => 'agencia', 'titulo' => 'Post fibra da empresa A', 'produto' => 'Social Media', 'coluna' => 'a_fazer', 'tema' => 'Post sobre a fibra de 700 Mega', 'tema_estado' => 'pendente', 'tema_versao' => 1, 'data_planejada' => '2026-11-03', 'postar_em' => null, 'rodadas_tema' => 0, 'rodadas_arte' => 0, 'versao' => null, 'tacita_ate' => null, 'ultima_decisao' => null],
                    ['id' => 'tarefa-a2', 'frente' => 'gaming', 'titulo' => 'Arte do campeonato da empresa A', 'produto' => null, 'coluna' => 'com_cliente', 'tema' => 'Chamada do campeonato', 'tema_estado' => 'aprovado', 'tema_versao' => 1, 'data_planejada' => '2026-11-05', 'postar_em' => '2026-11-05T14:00:00Z', 'rodadas_tema' => 0, 'rodadas_arte' => 0, 'versao' => ['id' => 'v-a2', 'numero' => 1, 'nome' => 'campeonato.png', 'tipo' => 'image/png', 'bytes' => 1200, 'comentario' => null, 'link' => $link('a2')], 'tacita_ate' => '2026-11-09', 'ultima_decisao' => null],
                    ['id' => 'tarefa-a3', 'frente' => 'agencia', 'titulo' => 'Reels em produção da empresa A', 'produto' => 'Social Media', 'coluna' => 'producao', 'tema' => 'Bastidores da equipe', 'tema_estado' => 'aprovado', 'tema_versao' => 1, 'data_planejada' => '2026-11-10', 'postar_em' => null, 'rodadas_tema' => 0, 'rodadas_arte' => 0, 'versao' => null, 'tacita_ate' => null, 'ultima_decisao' => null],
                ],
            ],
            'EMP-0002' => [
                'frentes' => [['chave' => 'agencia', 'nome' => 'Agência Crew']],
                'entregas' => [
                    ['id' => 'tarefa-b1', 'frente' => 'agencia', 'titulo' => 'Arte da empresa B', 'produto' => null, 'coluna' => 'com_cliente', 'tema' => null, 'tema_estado' => 'aprovado', 'tema_versao' => 1, 'data_planejada' => '2026-11-04', 'postar_em' => null, 'rodadas_tema' => 0, 'rodadas_arte' => 0, 'versao' => ['id' => 'v-b1', 'numero' => 1, 'nome' => 'b1.png', 'tipo' => 'image/png', 'bytes' => 900, 'comentario' => null, 'link' => $link('b1')], 'tacita_ate' => null, 'ultima_decisao' => null],
                ],
            ],
        ],
        'decisoes' => new stdClass,
    ];
}
function carregar(string $f): array {
    if (!is_file($f)) file_put_contents($f, json_encode(semente()));
    return json_decode(file_get_contents($f), true);
}
function gravar(string $f, array $s): void { file_put_contents($f, json_encode($s, JSON_UNESCAPED_UNICODE)); }
function responder(int $codigo, array $corpo): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}
function achar(array &$s, string $emp, string $id): ?int {
    foreach ($s['empresas'][$emp]['entregas'] ?? [] as $i => $e) if ($e['id'] === $id) return $i;
    return null;
}

$metodo = $_SERVER['REQUEST_METHOD'];
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$corpoBruto = file_get_contents('php://input');
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

// Rotas de teste (sem token).
if ($caminho === '/__test/log') {
    echo is_file($logFile) ? file_get_contents($logFile) : '';
    exit;
}
if ($caminho === '/__test/reset') {
    @unlink($stateFile); @unlink($logFile); @unlink($dir . '/falha');
    responder(200, ['ok' => true]);
}
if ($caminho === '/__test/avancar' && $metodo === 'POST') {
    // O que a equipe faria na Plataforma: tema_editado (volta a pendente), enviar_arte / nova_versao (com o cliente).
    $in = json_decode($corpoBruto, true);
    $s = carregar($stateFile);
    foreach (array_keys($s['empresas']) as $emp) {
        $i = achar($s, $emp, (string) ($in['tarefa'] ?? ''));
        if ($i === null) continue;
        $e = &$s['empresas'][$emp]['entregas'][$i];
        if ($in['acao'] === 'tema_editado') { $e['tema_estado'] = 'pendente'; $e['tema_versao'] = $e['rodadas_tema'] + 1; }
        if ($in['acao'] === 'enviar_arte' || $in['acao'] === 'nova_versao') {
            $n = ($e['versao']['numero'] ?? 0) + 1;
            $e['coluna'] = 'com_cliente';
            $e['versao'] = ['id' => 'v-' . $e['id'] . '-' . $n, 'numero' => $n, 'nome' => 'arte-v' . $n . '.png', 'tipo' => 'image/png', 'bytes' => 1000 + $n, 'comentario' => 'Versão ' . $n, 'link' => 'https://arquivos.example.test/a/' . $e['id'] . '/' . $n . '?t=assinado'];
        }
        gravar($stateFile, $s);
        responder(200, ['ok' => true]);
    }
    responder(404, ['erro' => 'tarefa']);
}

// Registro do que chegou (sem o token em si).
file_put_contents($logFile, json_encode([
    'metodo' => $metodo,
    'uri' => $_SERVER['REQUEST_URI'],
    'token_ok' => $auth === 'Bearer ' . TOKEN,
    'corpo' => json_decode($corpoBruto, true),
]) . "\n", FILE_APPEND);

if (is_file($dir . '/falha')) {
    responder((int) trim(file_get_contents($dir . '/falha')), ['erro' => 'falha de teste']);
}
if ($auth !== 'Bearer ' . TOKEN) responder(401, ['erro' => 'não autorizado']);

$s = carregar($stateFile);

if ($caminho === '/api/site/v1/entregas' && $metodo === 'GET') {
    $emp = (string) ($_GET['empresa'] ?? '');
    if (!isset($s['empresas'][$emp])) responder(404, ['erro' => 'não encontrado']);
    responder(200, ['empresa' => $emp, 'frente' => null, 'frentes' => $s['empresas'][$emp]['frentes'], 'entregas' => $s['empresas'][$emp]['entregas']]);
}

if ($caminho === '/api/site/v1/decisoes' && $metodo === 'POST') {
    $in = json_decode($corpoBruto, true);
    $campos = [];
    if (!is_array($in)) responder(400, ['erro' => 'corpo inválido', 'campos' => ['corpo']]);
    if (!isset($in['empresa']) || !is_string($in['empresa'])) $campos[] = 'empresa';
    if (!isset($in['tarefa']) || !is_string($in['tarefa']) || $in['tarefa'] === '') $campos[] = 'tarefa';
    if (!in_array($in['tipo'] ?? null, ['tema', 'arte'], true)) $campos[] = 'tipo';
    if (!is_int($in['versao'] ?? null) || $in['versao'] < 1) $campos[] = 'versao';
    if (!in_array($in['decisao'] ?? null, ['aprovada', 'ajuste'], true)) $campos[] = 'decisao';
    if (!filter_var($in['por'] ?? '', FILTER_VALIDATE_EMAIL)) $campos[] = 'por';
    if (empty($in['em']) || !is_string($in['em'])) $campos[] = 'em';
    if (($in['decisao'] ?? '') === 'ajuste' && empty($in['motivos']) && trim((string) ($in['sugestao'] ?? '')) === '') $campos[] = 'motivos';
    if ($campos) responder(400, ['erro' => 'corpo inválido', 'campos' => $campos]);

    $emp = $in['empresa'];
    $i = achar($s, $emp, $in['tarefa']);
    if ($i === null) responder(404, ['erro' => 'não encontrado']);
    $e = &$s['empresas'][$emp]['entregas'][$i];
    $chave = $in['tarefa'] . '|' . $in['tipo'] . '|' . $in['versao'];
    $decisoes = (array) $s['decisoes'];
    if (isset($decisoes[$chave])) {
        if ($decisoes[$chave] === $in['decisao']) responder(200, ['repetida' => true, 'entrega' => $e]);
        responder(409, ['erro' => 'conflito', 'codigo' => 'ja_decidida', 'mensagem' => 'Esta versão já foi decidida de outro jeito.']);
    }
    $conflito = fn($cod, $msg) => responder(409, ['erro' => 'conflito', 'codigo' => $cod, 'mensagem' => $msg]);

    if ($in['tipo'] === 'tema') {
        if ($e['tema'] === null) $conflito('sem_tema', 'Esta entrega não tem tema para decidir.');
        if ($e['tema_estado'] !== 'pendente') $conflito('tema_nao_pendente', 'O tema não está esperando você.');
        if ($in['versao'] !== $e['tema_versao']) $conflito('versao_desatualizada', 'O tema mudou. Veja a versão atual.');
        if ($in['decisao'] === 'ajuste' && $e['rodadas_tema'] >= 2) $conflito('limite_de_ajustes', 'O contrato prevê 2 rodadas de ajuste. A equipe foi avisada.');
        if ($in['decisao'] === 'aprovada') { $e['tema_estado'] = 'aprovado'; }
        else { $e['tema_estado'] = 'ajuste'; $e['rodadas_tema']++; }
        $e['ultima_decisao'] = ['tipo' => 'tema', 'decisao' => $in['decisao'], 'versao' => $in['versao'], 'em' => $in['em']];
    } else {
        if ($e['coluna'] !== 'com_cliente') $conflito('fora_de_com_cliente', 'Esta arte não está com você agora.');
        if ($e['tema'] !== null && $e['tema_estado'] !== 'aprovado') $conflito('tema_nao_aprovado', 'Aprove o tema antes da arte.');
        if ($e['versao'] === null || $in['versao'] !== $e['versao']['numero']) $conflito('versao_desatualizada', 'A arte mudou. Veja a versão atual.');
        if ($in['decisao'] === 'ajuste' && $e['rodadas_arte'] >= 2) $conflito('limite_de_ajustes', 'O contrato prevê 2 rodadas de ajuste. A equipe foi avisada.');
        if ($in['decisao'] === 'aprovada') { $e['coluna'] = 'entregue'; }
        else { $e['coluna'] = 'ajuste'; $e['rodadas_arte']++; }
        $e['tacita_ate'] = null;
        $e['ultima_decisao'] = ['tipo' => 'arte', 'decisao' => $in['decisao'], 'versao' => $in['versao'], 'em' => $in['em']];
    }
    $decisoes[$chave] = $in['decisao'];
    $s['decisoes'] = $decisoes;
    $resp = $e;
    gravar($stateFile, $s);
    responder(200, ['repetida' => false, 'entrega' => $resp]);
}

responder(404, ['erro' => 'não encontrado']);
