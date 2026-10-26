<?php
/**
 * Testes do limitador de tentativas (assets/php/ratelimit.php).
 * Rode: php tests/ratelimit_test.php
 */
$dir = sys_get_temp_dir() . '/crew-rl-test-' . bin2hex(random_bytes(4));
define('CREW_RL_DIR', $dir);
require __DIR__ . '/../assets/php/ratelimit.php';

$fails = 0;
$total = 0;
function check($name, $cond) {
    global $fails, $total;
    $total++;
    if (!$cond) { $fails++; echo "FALHOU: $name\n"; }
}
function rrmdir($d) {
    foreach (glob($d . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
        if (in_array(basename($f), ['.', '..'], true)) continue;
        is_dir($f) ? rrmdir($f) : unlink($f);
    }
    @rmdir($d);
}

/* --- IP do visitante --- */
check('REMOTE_ADDR publico vence cabecalhos forjados',
    crew_client_ip(['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4', 'HTTP_X_REAL_IP' => '5.6.7.8']) === '203.0.113.7');
check('REMOTE_ADDR privado usa X-Real-IP publico',
    crew_client_ip(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_REAL_IP' => '198.51.100.9']) === '198.51.100.9');
check('REMOTE_ADDR privado nao usa X-Forwarded-For',
    crew_client_ip(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9']) === '10.0.0.5');
check('REMOTE_ADDR privado com X-Real-IP invalido fica com REMOTE_ADDR',
    crew_client_ip(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_REAL_IP' => 'lixo']) === '10.0.0.5');
check('REMOTE_ADDR ausente vira 0', crew_client_ip([]) === '0');
check('REMOTE_ADDR invalido vira 0', crew_client_ip(['REMOTE_ADDR' => 'abc']) === '0');

/* --- chave: IPv6 agrupa por /64, IPv4 e igual --- */
check('IPv6 do mesmo /64 tem a mesma chave',
    crew_rl_key('2001:db8:1:2::1') === crew_rl_key('2001:db8:1:2:ffff::9'));
check('IPv6 de /64 diferente tem chave diferente',
    crew_rl_key('2001:db8:1:2::1') !== crew_rl_key('2001:db8:1:3::1'));
check('IPv4 diferentes tem chaves diferentes', crew_rl_key('203.0.113.7') !== crew_rl_key('203.0.113.8'));
check('chave nao contem o IP', strpos(crew_rl_key('203.0.113.7'), '203') === false);

/* --- crew_rl_hit: N por janela --- */
$t = 1000000;
$r = [];
for ($i = 0; $i < 5; $i++) $r[] = crew_rl_hit('form', 3, 60, '203.0.113.7', $t + $i);
check('3 primeiros passam, o resto bloqueia', $r === [true, true, true, false, false]);
check('outro IP nao e afetado', crew_rl_hit('form', 3, 60, '203.0.113.8', $t + 5) === true);
check('outro bucket nao e afetado', crew_rl_hit('outro', 3, 60, '203.0.113.7', $t + 5) === true);
check('depois da janela volta a passar', crew_rl_hit('form', 3, 60, '203.0.113.7', $t + 61) === true);

/* --- login: bloqueio por falhas --- */
$t = 2000000;
$ip = '198.51.100.20';
check('sem falhas nao bloqueia', crew_rl_blocked('login', 3, 900, $ip, $t) === 0);
crew_rl_fail('login', 900, $ip, $t);
crew_rl_fail('login', 900, $ip, $t + 10);
check('abaixo do limite nao bloqueia', crew_rl_blocked('login', 3, 900, $ip, $t + 11) === 0);
crew_rl_fail('login', 900, $ip, $t + 20);
$wait = crew_rl_blocked('login', 3, 900, $ip, $t + 30);
check('no limite bloqueia ate o fim da janela', $wait === 900 - 30);
check('outro IP nao e bloqueado', crew_rl_blocked('login', 3, 900, '198.51.100.21', $t + 30) === 0);
check('libera depois da janela', crew_rl_blocked('login', 3, 900, $ip, $t + 901) === 0);
crew_rl_reset('login', $ip, $t + 31);
check('acerto zera o contador', crew_rl_blocked('login', 3, 900, $ip, $t + 31) === 0);
crew_rl_fail('login', 900, $ip, $t + 40);
check('falha apos janela expirada recomeca a contagem', (function () use ($ip, $t) {
    crew_rl_fail('login', 900, $ip, $t + 2000);
    crew_rl_fail('login', 900, $ip, $t + 2001);
    return crew_rl_blocked('login', 3, 900, $ip, $t + 2002) === 0;
})());

/* --- armazenamento --- */
check('pasta criada', is_dir($dir));
check('pasta tem .htaccess negando tudo', is_file($dir . '/.htaccess') && strpos(file_get_contents($dir . '/.htaccess'), 'Require all denied') !== false);
$all = '';
foreach (glob($dir . '/*') as $f) $all .= file_get_contents($f);
check('nenhum IP em texto puro nos arquivos', strpos($all, '203.0.113') === false && strpos($all, '198.51.100') === false);

/* limpeza de entradas velhas */
$t = 3000000;
crew_rl_hit('limpeza', 5, 60, '192.0.2.1', $t);
crew_rl_hit('limpeza', 5, 60, '192.0.2.2', $t + 500);
$file = $dir . '/rl-limpeza.json';
$data = json_decode(file_get_contents($file), true);
check('entrada expirada e removida na proxima gravacao', count($data) === 1);

/* falha aberta: pasta impossivel de criar nao derruba o formulario */
$errlog = ini_get('error_log');
ini_set('error_log', '/dev/null');
check('falha aberta em hit', crew_rl_hit('x', 1, 60, '192.0.2.3', 1, '/proc/nao-existe/rl') === true && crew_rl_hit('x', 1, 60, '192.0.2.3', 2, '/proc/nao-existe/rl') === true);
check('falha aberta em blocked', crew_rl_blocked('x', 1, 60, '192.0.2.3', 3, '/proc/nao-existe/rl') === 0);
ini_set('error_log', $errlog);

rrmdir($dir);
echo $fails === 0 ? "OK: $total verificacoes passaram\n" : "$fails de $total falharam\n";
exit($fails === 0 ? 0 : 1);
