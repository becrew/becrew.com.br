<?php
/**
 * Testes das funções da área do cliente (assets/php/area/plataforma.php, a pontuação em produtos.php e o perfil do login em auth.php).
 * Rodar: php tests/area_test.php
 */
define('CREW_AUTH', true);
require_once __DIR__ . '/../assets/php/area/plataforma.php';
require_once __DIR__ . '/../assets/php/area/produtos.php';

$fails = 0;
$total = 0;
function check($label, $ok) {
    global $fails, $total;
    $total++;
    if (!$ok) $fails++;
    echo ($ok ? 'ok   ' : 'FALHA') . "  $label\n";
}

// --- código da empresa ---
check('empresa: EMP-0001 vale', crew_empresa_valida('EMP-0001'));
check('empresa: EMP-123456 vale', crew_empresa_valida('EMP-123456'));
foreach (['EMP-1', 'emp-0001', 'EMP-00A1', '', 'EMP-0001 ', "EMP-0001\n", null, 1234, ['EMP-0001']] as $ruim) {
    check('empresa: inválida ' . json_encode($ruim), !crew_empresa_valida($ruim));
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
$f = arquivo_config("<?php return ['url' => 'http://crm.exemplo.test', 'token' => 'crewsite_abc123'];");
check('config: url sem https → null', crew_area_ler_config($f) === null);
check('config: http em 127.0.0.1 só com permissão de teste', crew_area_ler_config($f, true) === null);
unlink($f);
$f = arquivo_config("<?php return ['url' => 'http://127.0.0.1:8799', 'token' => 'crewsite_abc123'];");
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
check('perfil: empresa válida sai', crew_profile_from(['hash' => 'x', 'empresa' => 'EMP-0007'], 'a@b.com') === ['email' => 'a@b.com', 'empresa' => 'EMP-0007']);
check('perfil: sem empresa → empresa null', crew_profile_from(['hash' => 'x'], 'a@b.com') === ['email' => 'a@b.com', 'empresa' => null]);
check('perfil: empresa fora do formato → null', crew_profile_from(['hash' => 'x', 'empresa' => 'qualquer'], 'a@b.com') === ['email' => 'a@b.com', 'empresa' => null]);

// --- textos em inglês: toda chave usada pelas páginas da área existe em common.js ou area.js ---
$raiz = dirname(__DIR__);
$dic = '';
foreach (['common', 'area'] as $n) $dic .= file_get_contents("$raiz/assets/js/lang/en/$n.js");
function tem_chave($dic, $k) { return strpos($dic, "'$k'") !== false; }
foreach (['assets/php/area/layout.php', 'painel.php', 'entregas.php', 'plataformas.php', 'produtos.php', 'pontos.php'] as $arq) {
    preg_match_all('/data-i18n="([^"<]+)"/', file_get_contents("$raiz/$arq"), $m);
    foreach (array_unique($m[1]) as $k) check("en: $arq usa '$k'", tem_chave($dic, $k));
}
foreach (['assets/js/crew-area.js', 'assets/js/crew-entregas.js'] as $arq) {
    preg_match_all("/\b(?:T|tx)\('([a-z][a-z0-9.]*)'/", file_get_contents("$raiz/$arq"), $m);
    foreach (array_unique($m[1]) as $k) { if (substr($k, -1) !== '.') check("en: $arq usa '$k'", tem_chave($dic, $k)); } // chave que termina em '.' é montada no código; ver $dinamicas
}
$dinamicas = [];
foreach (['tema', 'arte'] as $t) {
    foreach (['aprovar', 'ajuste'] as $b) $dinamicas[] = "ent.btn.$b.$t";
    foreach (['titulo', 'sugestao', 'ph'] as $b) $dinamicas[] = "ent.rej.$b.$t";
    for ($i = 1; $i <= 6; $i++) $dinamicas[] = "ent.motivo.$t.$i";
}
foreach (['inicio', 'entregas', 'relatorios', 'produtos', 'plataformas', 'soon'] as $n) $dinamicas[] = "area.nav.$n";
foreach (['inicio', 'entregas', 'plataformas', 'produtos', 'pontos'] as $n) { $dinamicas[] = "area.$n.meta"; $dinamicas[] = "area.$n.title"; }
foreach ($dinamicas as $k) check("en: chave dinâmica '$k'", tem_chave($dic, $k));

// --- pontos (XP) e níveis: cada produto contratado soma XP; o XP define o nível ---
$p = fn($xp, $link) => ['xp' => $xp, 'link' => $link];
check('xp: sem produto contratado → 0', crew_area_xp([$p(1000, null), $p(500, null)]) === 0);
check('xp: soma só os contratados', crew_area_xp([$p(1000, 'https://a'), $p(500, null), $p(1500, 'https://b')]) === 2500);
check('nível: 0 XP → Bronze, próximo Prata', ($n = crew_area_nivel(0))['atual'][0] === 'bronze' && $n['proximo'][0] === 'prata' && $n['falta'] === 1000 && $n['pct'] === 0);
check('nível: exatamente o mínimo já sobe (1000 → Prata)', crew_area_nivel(1000)['atual'][0] === 'prata');
check('nível: 1500 XP → Prata, faltam 1000 para Ouro, 33%', ($n = crew_area_nivel(1500))['atual'][0] === 'prata' && $n['falta'] === 1000 && $n['pct'] === 33);
check('nível: no último não há próximo e a barra fica cheia', ($n = crew_area_nivel(99999))['proximo'] === null && $n['pct'] === 100 && $n['falta'] === 0);
check('nível: os mínimos crescem e o primeiro é 0', (function () { $m = array_column(crew_area_niveis(), 2); $s = $m; sort($s); return $m[0] === 0 && $m === $s && count(array_unique($m)) === count($m); })());
check('produtos: todo produto tem categoria conhecida e XP positivo', (function () { $c = crew_area_categorias(); foreach (crew_area_produtos() as $x) if (!isset($c[$x['categoria']]) || $x['xp'] <= 0) return false; return true; })());
check('xp: número no formato brasileiro', crew_area_num(1500) === '1.500');

// --- páginas logadas, etapa 1 dos pontos (decisão de Rafael, 2026-10-30): os níveis ficam à vista, mas sem placar
// igual para todos, sem benefício e sem "Contratado" fixo. Cada página roda no PHP de linha de comando, numa cópia
// temporária só com auth.php, area/ e as páginas, com um users.php de mentira (o real nunca é lido) ---
function pagina_logada(string $pagina, string $email): string {
    static $tmp = null;
    $raiz = dirname(__DIR__);
    if ($tmp === null) {
        $tmp = sys_get_temp_dir() . '/crew-area-' . bin2hex(random_bytes(4));
        mkdir("$tmp/assets/php/area", 0700, true);
        mkdir("$tmp/sessoes", 0700);
        foreach (['painel.php', 'entregas.php', 'plataformas.php', 'produtos.php', 'pontos.php', 'assets/php/auth.php'] as $f) copy("$raiz/$f", "$tmp/$f");
        foreach (glob("$raiz/assets/php/area/*.php") as $f) copy($f, "$tmp/assets/php/area/" . basename($f));
        file_put_contents("$tmp/assets/php/users.php", "<?php return ['a@example.com' => ['hash' => 'x', 'empresa' => 'EMP-0001'], 'teste@example.com' => ['hash' => 'x']];");
        file_put_contents("$tmp/login.php", "<?php session_save_path(__DIR__ . '/sessoes'); session_start(); \$_SESSION['crew_user'] = getenv('CREW_TESTE_EMAIL');");
        register_shutdown_function(function () use ($tmp) { exec('rm -rf ' . escapeshellarg($tmp)); });
    }
    $cmd = 'CREW_TESTE_EMAIL=' . escapeshellarg($email) . ' ' . escapeshellarg(PHP_BINARY)
        . ' -d auto_prepend_file=' . escapeshellarg("$tmp/login.php") . ' ' . escapeshellarg("$tmp/$pagina") . ' 2>&1';
    return (string) shell_exec($cmd);
}
$telas = [
    'Início' => pagina_logada('painel.php', 'a@example.com'),
    'Entregas' => pagina_logada('entregas.php', 'a@example.com'),
    'Plataformas' => pagina_logada('plataformas.php', 'a@example.com'),
    'Plataformas sem empresa' => pagina_logada('plataformas.php', 'teste@example.com'),
    'Produtos' => pagina_logada('produtos.php', 'a@example.com'),
    'Produtos sem empresa' => pagina_logada('produtos.php', 'teste@example.com'),
    'Pontos' => pagina_logada('pontos.php', 'a@example.com'),
    'Pontos sem empresa' => pagina_logada('pontos.php', 'teste@example.com'),
];
$comEmpresa = ['Início', 'Entregas', 'Plataformas', 'Produtos', 'Pontos'];
foreach ($telas as $nome => $html) {
    check("tela $nome: abre (área logada)", strpos($html, 'app-nav') !== false && strpos($html, 'Fatal') === false && strpos($html, 'Warning') === false);
    check("tela $nome: nenhum número de XP", preg_match('/\d[\d.]*\s*(<\/[a-z]+>\s*)*XP/', $html) === 0);
    check("tela $nome: nenhum benefício de nível", strpos($html, 'beneficio') === false && count(array_filter(crew_area_niveis(), fn($n) => strpos($html, $n[3]) !== false)) === 0);
    check("tela $nome: nenhum \"Contratado\" nem \"Acessar\"", strpos($html, 'Contratado') === false && strpos($html, 'area.prod.acessar') === false);
    check("tela $nome: nenhum preço", preg_match('/R\$\s*\d/', $html) === 0);
    check("tela $nome: nada aponta mais para os pontos em Plataformas nem no Início", strpos($html, '#pontos') === false);
    // Pontos só no menu (Rafael, 2026-11-11: "deixe apenas no menu e possibilite levar para uma pagina que fala apenas dos pontos").
    check("tela $nome: o bloco Pontos e níveis da barra lateral leva à página de pontos", preg_match('/<a class="app-nivel" href="pontos\.php"/', $html) === 1);
    // Menu (decisões de Rafael, 2026-11-11): Produtos ganha página; Relatórios continua "em breve"; sem empresa vê Produtos e Plataformas.
    check("tela $nome: menu leva a Produtos e a Plataformas", strpos($html, 'class="app-nav__link" href="produtos.php"') !== false && strpos($html, 'class="app-nav__link" href="plataformas.php"') !== false);
    check("tela $nome: Produtos não está mais \"em breve\"", preg_match('/is-soon[^>]*>(?:(?!<\/span><em).)*area\.nav\.produtos/s', $html) === 0);
    if (in_array($nome, $comEmpresa, true)) {
        check("tela $nome: Relatórios continua \"em breve\"", preg_match('/is-soon[^>]*><i data-lucide="file-text"><\/i><span data-i18n="area\.nav\.relatorios">/', $html) === 1);
    } else {
        check("tela $nome: sem empresa, o menu não tem Início nem Entregas", strpos($html, 'href="painel.php"') === false && strpos($html, 'href="entregas.php"') === false);
    }
}
// O título do topo bate com o nome do menu.
foreach (['Início' => ['inicio', 'Início'], 'Entregas' => ['entregas', 'Entregas'], 'Plataformas' => ['plataformas', 'Plataformas'], 'Produtos' => ['produtos', 'Produtos'], 'Pontos' => ['pontos', 'Pontos e níveis']] as $nome => [$k, $t]) {
    check("tela $nome: o topo diz \"$t\"", strpos($telas[$nome], 'data-i18n="area.' . $k . '.title">' . $t . '</h1>') !== false);
}

// Pontos e níveis numa página só deles (Rafael, 2026-11-11), aberta com e sem empresa, ainda na etapa 1.
foreach (['Pontos', 'Pontos sem empresa'] as $nome) {
    $h = $telas[$nome];
    check("tela $nome: painel de pontos", strpos($h, 'class="xp-painel"') !== false);
    check("tela $nome: mostra os quatro níveis", count(array_filter(crew_area_niveis(), fn($n) => strpos($h, 'data-i18n="area.nivel.' . $n[0] . '"') !== false)) === 4);
    check("tela $nome: explica como funciona, sem benefício", strpos($h, 'area.xp.como.pontos') !== false && strpos($h, 'area.xp.como.texto') === false);
    check("tela $nome: avisa que a contagem começa em breve", strpos($h, 'class="xp-painel__titulo" id="xp-titulo" data-i18n="area.xp.embreve"') !== false);
    check("tela $nome: o bloco da barra lateral fica marcado como a página atual", preg_match('/<a class="app-nivel" href="pontos\.php"[^>]*aria-current="page"/', $h) === 1);
}
$ini = $telas['Início'];
check('tela Início: nada de pontos no conteúdo (só o bloco do menu)', strpos($ini, 'xp-painel') === false && strpos($ini, 'xp-faixa') === false && strpos($ini, 'area.xp.como') === false);
foreach (['Início', 'Entregas', 'Plataformas', 'Plataformas sem empresa', 'Produtos', 'Produtos sem empresa'] as $nome) {
    check("tela $nome: sem o painel de pontos", strpos($telas[$nome], 'xp-painel') === false);
    check("tela $nome: o bloco do menu não fica marcado", preg_match('/<a class="app-nivel"[^>]*aria-current/', $telas[$nome]) === 0);
}

// Plataformas: só o Side Quest, com "Entrar no Side Quest" para todos e sem o selo "Contratado" (Rafael, 2026-11-11).
$sq = 'https://side-quest-drab-two.vercel.app/dashboard';
check('plataformas: só o Side Quest', function_exists('crew_area_plataformas') && array_column(crew_area_plataformas(), 'chave') === ['sidequest']);
foreach (['Plataformas', 'Plataformas sem empresa'] as $nome) {
    $h = $telas[$nome];
    check("tela $nome: um cartão só", substr_count($h, 'class="prod-card ') === 1);
    check("tela $nome: botão Entrar no Side Quest", strpos($h, 'href="' . $sq . '"') !== false && strpos($h, 'data-i18n="area.plat.entrar">Entrar no Side Quest') !== false);
    check("tela $nome: avisa que o acesso é com o login do Side Quest", strpos($h, 'data-i18n="area.plat.login"') !== false);
    check("tela $nome: nenhum produto do catálogo", stripos($h, 'EXITLAG') === false && strpos($h, 'GAMING.CREW') === false && strpos($h, 'data-prod-cat') === false);
}

// Produtos: três abas, Serviços, Games e SVAs (Rafael, 2026-11-11). Speakr vira Relatórios; Ruller oculto. Marketing num cartão só e Consultoria com o que entrega (Rafael, 2026-11-11).
check('categorias: Serviços, Games e SVAs', array_keys(crew_area_categorias()) === ['servicos', 'games', 'svas']);
$cat = array_column(crew_area_produtos(), 'categoria', 'chave');
check('produtos: cada um na aba certa', $cat === ['consultoria' => 'servicos', 'marketing' => 'servicos', 'ruller' => 'servicos', 'timeesports' => 'games', 'hubipc' => 'games', 'exitlag' => 'svas', 'abyago' => 'svas', 'kaspersky' => 'svas', 'socialcomics' => 'svas']);
check('produtos: Speakr não está no catálogo', !isset($cat['speakr']));
check('produtos: Ruller continua no catálogo, marcado como oculto', (array_column(crew_area_produtos(), null, 'chave')['ruller']['oculto'] ?? false) === true);
// Time de E-Sports com a mesma imagem do Side Quest (public/headers/topo-playbook.png), em WebP (Rafael, 2026-11-11).
$fotoEsports = array_column(crew_area_produtos(), 'foto', 'chave')['timeesports'] ?? '';
check('produtos: Time de E-Sports usa a imagem do Side Quest', $fotoEsports === 'assets/img/time-esports.webp');
check('produtos: a imagem do Time de E-Sports existe e é WebP', is_file("$raiz/$fotoEsports") && substr((string) file_get_contents("$raiz/$fotoEsports", false, null, 8, 4), 0, 4) === 'WEBP');
$en = (string) file_get_contents("$raiz/assets/js/lang/en/area.js");
check('inglês: Consultoria e Marketing sem "for ISPs"', stripos($en, 'for ISPs') === false && strpos($en, "'painel.consultoria.title': 'CONSULTING'") !== false && strpos($en, "'painel.marketing.title': 'MARKETING'") !== false);
check('inglês: Abya Go com o crédito "Abya technology"', strpos($en, "'painel.abyago.title': 'ABYA GO'") !== false && stripos($en, 'white-label') !== false && strpos($en, 'Abya technology.') !== false);
check('produtos: nenhum tem link de acesso (o Side Quest é plataforma)', count(array_filter(array_column(crew_area_produtos(), 'link'))) === 0);
$situacao = ['CONSULTORIA' => 'contratar', 'MARKETING' => 'contratar', 'TIME DE E-SPORTS' => 'contratar', 'HUB IPC' => 'contratar', 'EXITLAG' => 'contratar', 'ABYA GO' => 'contratar', 'KASPERSKY' => 'breve', 'SOCIAL COMICS' => 'breve'];
foreach (['Produtos', 'Produtos sem empresa'] as $nome) {
    $h = $telas[$nome];
    check("tela $nome: abas Serviços, Games e SVAs", strpos($h, 'data-prod-cat="servicos"') !== false && strpos($h, 'data-prod-cat="games"') !== false && strpos($h, 'data-prod-cat="svas"') !== false);
    check("tela $nome: sem as abas antigas", strpos($h, 'data-prod-cat="gaming"') === false && strpos($h, 'data-prod-cat="agencia"') === false && strpos($h, 'data-prod-cat="tech"') === false);
    check("tela $nome: oito cartões", substr_count($h, 'class="prod-card ') === 8);
    foreach ($situacao as $prod => $sit) {
        check("tela $nome: $prod aparece como $sit", preg_match('/class="prod-card is-' . $sit . '"[^>]*>(?:(?!<\/article>).)*>' . preg_quote($prod, '/') . '<\/h2>/s', $h) === 1);
    }
    // Games: o "IPC" virou o Time de E-Sports, igual ao Side Quest (Rafael, 2026-11-11; src/lib/products.ts e dashboard/produtos do Side Quest).
    check("tela $nome: sem o cartão IPC", strpos($h, '>IPC</h2>') === false && strpos($h, 'IP Champions') === false);
    check("tela $nome: Time de E-Sports com o texto do Side Quest", strpos($h, 'Patrocínio, presença em campeonatos e conteúdo com os jogadores.') !== false);
    // Hub IPC: comparador de planos gamer, com o chamado para o provedor fazer parte e o botão "Quero contratar" (Rafael, 2026-11-11).
    check("tela $nome: Hub IPC explica o comparador de planos gamer", strpos($h, 'comparador de planos de internet gamer') !== false);
    check("tela $nome: Hub IPC chama o provedor para fazer parte", strpos($h, 'Coloque os planos gamer do seu provedor no Hub IPC e faça parte.') !== false);
    check("tela $nome: Hub IPC sem o texto provisório", strpos($h, 'Um novo produto da Crew para o público gamer') === false);
    // Serviços: um cartão só de marketing, sem separar os planos, e a Consultoria dizendo o que entrega (Rafael, 2026-11-11). Sem "para ISPs" nos nomes (Rafael, 2026-11-11).
    check("tela $nome: sem os planos Essencial e Profissional separados", strpos($h, 'PLANO ESSENCIAL') === false && strpos($h, 'PLANO PROFISSIONAL') === false && strpos($h, 'PLANO DE CONSULTORIA') === false);
    check("tela $nome: Marketing diz o que entrega", strpos($h, 'anúncios na Meta e no Google por área de cobertura') !== false);
    check("tela $nome: Consultoria diz o que entrega", strpos($h, 'Consultoria de gestão para o seu provedor, em seis frentes') !== false);
    check("tela $nome: sem o texto provisório da Consultoria", strpos($h, 'Os detalhes chegam em breve') === false);
    check("tela $nome: nomes sem \"para ISPs\"", stripos($h, 'para ISPs') === false);
    // SVAs: Abya Go, aprovado pela Abya (Rafael, 2026-11-11). Crédito "Tecnologia Abya" obrigatório; sem preço; logo ainda não veio.
    $abya = preg_match('/<article class="prod-card is-contratar" data-prod="contratar" data-cat="svas"(?:(?!<\/article>).)*>ABYA GO<\/h2>(?:(?!<\/article>).)*<\/article>/s', $h, $mAbya) ? $mAbya[0] : '';
    check("tela $nome: Abya Go em SVAs, com \"Quero contratar\"", $abya !== '' && strpos($abya, 'Quero contratar') !== false);
    check("tela $nome: Abya Go é white label e dá o crédito \"Tecnologia Abya\"", strpos($abya, 'white label') !== false && strpos($abya, 'Tecnologia Abya.') !== false);
    check("tela $nome: Abya Go sem preço e sem \"em breve\"", $abya !== '' && strpos($abya, 'R$') === false && stripos($abya, 'em breve') === false);
    check("tela $nome: Abya Go com o ícone de gamepad enquanto o logo não vem", strpos($abya, 'prod-card__foto is-marca') !== false && strpos($abya, 'data-lucide="gamepad-2"') !== false);
    check("tela $nome: sem Speakr, Ruller nem Side Quest", stripos($h, 'speakr') === false && strpos($h, 'RULLER.CREW') === false && strpos($h, 'side-quest') === false);
}

// --- repositório público: nada de plano interno nem de caminho do servidor ---
$readme = file_get_contents("$raiz/README.md");
check('público: README não traz o caminho do token no servidor', strpos($readme, 'domains/') === false);
check('público: README não aponta para o plano interno', strpos($readme, 'PLANO-AREA') === false);
check('público: o plano interno não está em docs/', !file_exists("$raiz/docs/PLANO-AREA-FASE1.md"));

// --- tela de Entregas: o formulário aberto não pode travar a atualização depois de um redesenho ---
$js = file_get_contents("$raiz/assets/js/crew-entregas.js");
check('entregas.js: desenhar() zera formAberto', preg_match('/function desenhar\(\)\s*\{[^}]*formAberto = false/s', $js) === 1);
check('entregas.js: resposta de busca antiga é descartada (geração)', strpos($js, 'geracao') !== false);
check('entregas.js: 200 sem entrega recarrega a lista', preg_match('/status === 200[^;]*;?\s*[^\n]*buscar\(true\)/s', $js) === 1);

// --- ícones: a medalha (award) é a da Lucide, um círculo com as fitas, e cabe na caixa 24x24 ---
$icones = file_get_contents("$raiz/assets/js/lucide-local.js");
$award = preg_match("/'award':'([^']*)'/", $icones, $m) ? $m[1] : '';
check('ícone award: tem o círculo da medalha', strpos($award, 'a6 6 0') !== false);
check('ícone award: não é a estrela torta de antes', $award !== '' && strpos($award, 'L11.33.96') === false && strpos($award, '1.279z') === false);

echo "\n" . ($total - $fails) . " de $total passaram\n";
exit($fails ? 1 : 0);
