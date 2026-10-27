<?php
/**
 * Testes das funções da área do cliente (assets/php/area/plataforma.php e o perfil do login em auth.php).
 * Rodar: php tests/area_test.php
 */
define('CREW_AUTH', true);
require __DIR__ . '/../assets/php/area/plataforma.php';

$fails = 0;
$total = 0;
function check($label, $ok) {
    global $fails, $total;
    $total++;
    if (!$ok) $fails++;
    echo ($ok ? 'ok   ' : 'FALHA') . "  $label\n";
}

// --- código da empresa ---
check('empresa: EMP-0001 vale', crew_area_empresa_valida('EMP-0001'));
check('empresa: EMP-123456 vale', crew_area_empresa_valida('EMP-123456'));
foreach (['EMP-1', 'emp-0001', 'EMP-00A1', '', 'EMP-0001 ', "EMP-0001\n", null, 1234, ['EMP-0001']] as $ruim) {
    check('empresa: inválida ' . json_encode($ruim), !crew_area_empresa_valida($ruim));
}

// --- arquivo do token ---
function arquivo_config($conteudo) {
    $f = tempnam(sys_get_temp_dir(), 'cfg');
    file_put_contents($f, $conteudo);
    return $f;
}
$f = arquivo_config("<?php return ['url' => 'https://crm.exemplo.test/', 'token' => 'crewsite_abc123'];");
$c = crew_area_ler_config($f);
check('config: lê url (sem barra no fim) e token', $c === ['url' => 'https://crm.exemplo.test', 'token' => 'crewsite_abc123']);
unlink($f);
check('config: arquivo ausente → null', crew_area_ler_config('/nao/existe/crew-plataforma.php') === null);
$f = arquivo_config("<?php return ['url' => 'http://crm.exemplo.test', 'token' => 'crewsite_abc'];");
check('config: url sem https → null', crew_area_ler_config($f) === null);
check('config: http em 127.0.0.1 só com permissão de teste', crew_area_ler_config($f, true) === null);
unlink($f);
$f = arquivo_config("<?php return ['url' => 'http://127.0.0.1:8799', 'token' => 'crewsite_abc'];");
check('config: http://127.0.0.1 sem permissão de teste → null', crew_area_ler_config($f) === null);
check('config: http://127.0.0.1 com permissão de teste vale', crew_area_ler_config($f, true) !== null);
unlink($f);
$f = arquivo_config("<?php return ['url' => 'https://crm.exemplo.test', 'token' => 'outra_coisa'];");
check('config: token sem o prefixo crewsite_ → null', crew_area_ler_config($f) === null);
unlink($f);
$f = arquivo_config("<?php return 'isso nao e uma lista';");
check('config: retorno que não é lista → null', crew_area_ler_config($f) === null);
unlink($f);
$f = arquivo_config("<?php this is not php");
check('config: arquivo quebrado → null (sem derrubar a página)', crew_area_ler_config($f) === null);
unlink($f);

// --- o que espera o cliente (tabela do SITE-ENTREGAS.md) ---
function ent($sobre = []) {
    return array_merge([
        'id' => 't1', 'frente' => 'agencia', 'titulo' => 'x', 'produto' => null, 'coluna' => 'producao',
        'tema' => 'Um tema', 'tema_estado' => 'aprovado', 'tema_versao' => 1, 'rodadas_tema' => 0, 'rodadas_arte' => 0,
        'versao' => null, 'tacita_ate' => null, 'ultima_decisao' => null,
    ], $sobre);
}
$v1 = ['id' => 'v', 'numero' => 1, 'nome' => 'a.png', 'tipo' => 'image/png', 'bytes' => 1, 'comentario' => null, 'link' => 'https://x.test/a'];
check('espera: tema pendente → tema', crew_area_acao_cliente(ent(['tema_estado' => 'pendente'])) === 'tema');
check('espera: tema pendente em "a fazer" → tema', crew_area_acao_cliente(ent(['tema_estado' => 'pendente', 'coluna' => 'a_fazer'])) === 'tema');
check('espera: tema com ajuste pedido → nada (a Crew revisa)', crew_area_acao_cliente(ent(['tema_estado' => 'ajuste'])) === null);
check('espera: com o cliente, tema aprovado, com arquivo → arte', crew_area_acao_cliente(ent(['coluna' => 'com_cliente', 'versao' => $v1])) === 'arte');
check('espera: com o cliente, sem tema, com arquivo → arte', crew_area_acao_cliente(ent(['coluna' => 'com_cliente', 'tema' => null, 'versao' => $v1])) === 'arte');
check('espera: com o cliente, tema ainda não aprovado → só o tema (se pendente)', crew_area_acao_cliente(ent(['coluna' => 'com_cliente', 'tema_estado' => 'pendente', 'versao' => $v1])) === 'tema');
check('espera: com o cliente, tema com ajuste → nada ("aprove o tema antes" não é ação)', crew_area_acao_cliente(ent(['coluna' => 'com_cliente', 'tema_estado' => 'ajuste', 'versao' => $v1])) === null);
check('espera: com o cliente mas sem arquivo → nada', crew_area_acao_cliente(ent(['coluna' => 'com_cliente'])) === null);
foreach (['a_fazer', 'producao', 'aprovacao_interna', 'ajuste', 'entregue'] as $col) {
    check("espera: coluna $col com tema aprovado → nada", crew_area_acao_cliente(ent(['coluna' => $col, 'versao' => $col === 'ajuste' || $col === 'entregue' ? $v1 : null])) === null);
}
check('espera: entregue com tema pendente (dado estranho) → nada', crew_area_acao_cliente(ent(['coluna' => 'entregue', 'tema_estado' => 'pendente', 'versao' => $v1])) === null);

$corpo = ['empresa' => 'EMP-0001', 'frentes' => [], 'entregas' => [
    ent(['id' => 'a', 'tema_estado' => 'pendente']),
    ent(['id' => 'b', 'coluna' => 'com_cliente', 'versao' => $v1]),
    ent(['id' => 'c']),
]];
$anot = crew_area_anotar($corpo);
check('anotar: cada entrega ganha acao_cliente', array_column($anot['entregas'], 'acao_cliente') === ['tema', 'arte', null]);
check('anotar: esperando conta uma vez cada entrega', $anot['esperando'] === 2);
check('anotar: o resto do corpo fica igual', $anot['empresa'] === 'EMP-0001' && $anot['entregas'][0]['id'] === 'a');
check('anotar: sem lista de entregas → lista vazia e zero', crew_area_anotar(['empresa' => 'EMP-0001'])['esperando'] === 0);

// --- decisão do navegador → corpo para a Plataforma ---
$agora = '2026-11-02T12:00:00+00:00';
$ok = crew_area_montar_decisao(['tarefa' => 'tarefa-a1', 'tipo' => 'tema', 'versao' => 1, 'decisao' => 'aprovada'], 'EMP-0001', 'cli@isp.com.br', $agora);
check('decisão: aprovar tema monta o corpo com empresa e por do login', $ok === ['empresa' => 'EMP-0001', 'tarefa' => 'tarefa-a1', 'tipo' => 'tema', 'versao' => 1, 'decisao' => 'aprovada', 'motivos' => [], 'sugestao' => '', 'por' => 'cli@isp.com.br', 'em' => $agora]);
$x = crew_area_montar_decisao(['empresa' => 'EMP-0002', 'por' => 'outro@x.com', 'em' => '2000-01-01', 'tarefa' => 't', 'tipo' => 'arte', 'versao' => 2, 'decisao' => 'aprovada'], 'EMP-0001', 'cli@isp.com.br', $agora);
check('decisão: empresa, por e em do navegador são ignorados', $x['empresa'] === 'EMP-0001' && $x['por'] === 'cli@isp.com.br' && $x['em'] === $agora);
$x = crew_area_montar_decisao(['tarefa' => 't', 'tipo' => 'arte', 'versao' => 1, 'decisao' => 'ajuste', 'motivos' => ['Cores', 'Texto', 5, '', str_repeat('é', 200)], 'sugestao' => 'Trocar a foto'], 'EMP-0001', 'cli@isp.com.br', $agora);
check('decisão: ajuste com motivos e sugestão passa', $x !== null && $x['decisao'] === 'ajuste' && $x['sugestao'] === 'Trocar a foto');
check('decisão: motivos viram texto, sem vazios e com até 80 caracteres', $x['motivos'] === ['Cores', 'Texto', '5', str_repeat('é', 80)]);
$x = crew_area_montar_decisao(['tarefa' => 't', 'tipo' => 'arte', 'versao' => 1, 'decisao' => 'ajuste', 'motivos' => array_fill(0, 12, 'm')], 'EMP-0001', 'cli@isp.com.br', $agora);
check('decisão: no máximo 8 motivos', count($x['motivos']) === 8);
$x = crew_area_montar_decisao(['tarefa' => 't', 'tipo' => 'arte', 'versao' => 1, 'decisao' => 'ajuste', 'sugestao' => str_repeat('ã', 1500)], 'EMP-0001', 'cli@isp.com.br', $agora);
check('decisão: sugestão com até 1000 caracteres', $x !== null && mb_strlen($x['sugestao']) === 1000);
check('decisão: ajuste sem motivo e sem sugestão é recusado', crew_area_montar_decisao(['tarefa' => 't', 'tipo' => 'arte', 'versao' => 1, 'decisao' => 'ajuste', 'motivos' => [], 'sugestao' => '  '], 'EMP-0001', 'cli@isp.com.br', $agora) === null);
$base = ['tarefa' => 't', 'tipo' => 'tema', 'versao' => 1, 'decisao' => 'aprovada'];
foreach ([['tipo', 'foto'], ['tipo', null], ['decisao', 'talvez'], ['decisao', null], ['versao', 0], ['versao', -1], ['versao', 'abc'], ['tarefa', ''], ['tarefa', null], ['tarefa', str_repeat('a', 65)], ['tarefa', ['x']]] as [$campo, $valor]) {
    $e = $base; $e[$campo] = $valor;
    check("decisão: $campo=" . json_encode($valor) . ' é recusado', crew_area_montar_decisao($e, 'EMP-0001', 'cli@isp.com.br', $agora) === null);
}
check('decisão: versão numérica em texto ("2") é aceita como inteiro', (crew_area_montar_decisao(array_merge($base, ['versao' => '2']), 'EMP-0001', 'cli@isp.com.br', $agora)['versao'] ?? 0) === 2);
check('decisão: empresa inválida na sessão é recusada', crew_area_montar_decisao($base, 'EMP-1', 'cli@isp.com.br', $agora) === null);
check('decisão: e-mail inválido na sessão é recusado', crew_area_montar_decisao($base, 'EMP-0001', 'nao-e-email', $agora) === null);

// --- perfil do login (auth.php) ---
require __DIR__ . '/../assets/php/auth.php';
check('perfil: empresa válida sai', crew_profile_from(['hash' => 'x', 'empresa' => 'EMP-0007'], 'a@b.com') === ['email' => 'a@b.com', 'empresa' => 'EMP-0007']);
check('perfil: sem empresa → empresa null', crew_profile_from(['hash' => 'x'], 'a@b.com') === ['email' => 'a@b.com', 'empresa' => null]);
check('perfil: empresa fora do formato → null', crew_profile_from(['hash' => 'x', 'empresa' => 'qualquer'], 'a@b.com') === ['email' => 'a@b.com', 'empresa' => null]);

echo "\n" . ($total - $fails) . " de $total passaram\n";
exit($fails ? 1 : 0);
