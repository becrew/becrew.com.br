<?php
/**
 * Crew - limite de tentativas por IP (usado por login.php, contact.php, newsletter.php e lead.php).
 *
 * Os contadores ficam em assets/php/data/ (fora do repositório, bloqueada no .htaccess e com
 * .htaccess próprio). Guardamos só um hash do IP. Se a pasta não puder ser gravada, o limite
 * deixa de valer (falha aberta) e o erro vai para o log: formulário fora do ar é pior que sem limite.
 */

if (!defined('CREW_RL_DIR')) define('CREW_RL_DIR', __DIR__ . '/data');

/**
 * IP do visitante. A Hostinger entrega o IP real em REMOTE_ADDR (confirmar no servidor).
 * Só se REMOTE_ADDR vier como IP privado ou reservado (proxy interno) usamos X-Real-IP,
 * que a CDN define. X-Forwarded-For nunca é usado: o visitante consegue forjar.
 */
function crew_client_ip(?array $server = null): string {
    $server = $server ?? $_SERVER;
    $public = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    $remote = isset($server['REMOTE_ADDR']) && is_string($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : '';
    if (!filter_var($remote, FILTER_VALIDATE_IP)) return '0';
    if (!filter_var($remote, FILTER_VALIDATE_IP, $public)) {
        $real = isset($server['HTTP_X_REAL_IP']) && is_string($server['HTTP_X_REAL_IP']) ? trim($server['HTTP_X_REAL_IP']) : '';
        if (filter_var($real, FILTER_VALIDATE_IP, $public)) return $real;
    }
    return $remote;
}

/* Prepara a pasta dos contadores (com .htaccess negando tudo e o sal do hash). Devolve false se não der. */
function crew_rl_prepare(string $dir): bool {
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return false;
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    return is_writable($dir);
}

/* Chave do contador: hash do IP (IPv6 agrupado por /64, para trocar de endereço não burlar o limite). */
function crew_rl_key(string $ip, ?string $dir = null): string {
    $dir = $dir ?? CREW_RL_DIR;
    $bin = @inet_pton($ip);
    if ($bin !== false && strlen($bin) === 16) $ip = bin2hex(substr($bin, 0, 8)) . '/64';
    $saltFile = $dir . '/salt.txt';
    $salt = is_file($saltFile) ? (string) @file_get_contents($saltFile) : '';
    if ($salt === '' && crew_rl_prepare($dir)) {
        $salt = bin2hex(random_bytes(16));
        @file_put_contents($saltFile, $salt);
    }
    return substr(hash('sha256', $salt . '|' . $ip), 0, 32);
}

/**
 * Lê, altera e grava o arquivo do bucket com trava. $fn recebe o array (chave => ['n','t','w']) por
 * referência e devolve o resultado. Devolve null se a pasta não puder ser usada.
 */
function crew_rl_edit(string $bucket, int $now, ?string $dir, callable $fn) {
    $dir = $dir ?? CREW_RL_DIR;
    if (!crew_rl_prepare($dir)) {
        error_log('crew ratelimit: pasta sem permissão de escrita: ' . $dir);
        return null;
    }
    $fp = @fopen($dir . '/rl-' . preg_replace('/[^a-z0-9_-]/i', '', $bucket) . '.json', 'c+');
    if (!$fp) {
        error_log('crew ratelimit: não consegui abrir o arquivo do bucket ' . $bucket);
        return null;
    }
    flock($fp, LOCK_EX);
    $data = json_decode(stream_get_contents($fp) ?: '{}', true);
    if (!is_array($data)) $data = [];
    foreach ($data as $k => $r) {
        if (!isset($r['t'], $r['w']) || $now - $r['t'] >= $r['w']) unset($data[$k]);
    }
    $out = $fn($data);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $out;
}

/* Conta uma tentativa. true = pode seguir; false = passou de $limit tentativas na janela de $window segundos. */
function crew_rl_hit(string $bucket, int $limit, int $window, ?string $ip = null, ?int $now = null, ?string $dir = null): bool {
    $now = $now ?? time();
    $key = crew_rl_key($ip ?? crew_client_ip(), $dir);
    $allowed = crew_rl_edit($bucket, $now, $dir, function (array &$data) use ($key, $window, $now, $limit) {
        $r = $data[$key] ?? ['n' => 0, 't' => $now, 'w' => $window];
        $r['n']++;
        $data[$key] = $r;
        return $r['n'] <= $limit;
    });
    return $allowed !== false;
}

/* Segundos que faltam para liberar este IP (0 = não está bloqueado). Só consulta, não conta. */
function crew_rl_blocked(string $bucket, int $limit, int $window, ?string $ip = null, ?int $now = null, ?string $dir = null): int {
    $now = $now ?? time();
    $key = crew_rl_key($ip ?? crew_client_ip(), $dir);
    $wait = crew_rl_edit($bucket, $now, $dir, function (array &$data) use ($key, $limit, $now) {
        $r = $data[$key] ?? null;
        return $r && $r['n'] >= $limit ? $r['w'] - ($now - $r['t']) : 0;
    });
    return (int) $wait;
}

/* Registra uma falha (ex.: senha errada). A janela começa na primeira falha. */
function crew_rl_fail(string $bucket, int $window, ?string $ip = null, ?int $now = null, ?string $dir = null): void {
    $now = $now ?? time();
    $key = crew_rl_key($ip ?? crew_client_ip(), $dir);
    crew_rl_edit($bucket, $now, $dir, function (array &$data) use ($key, $window, $now) {
        $r = $data[$key] ?? ['n' => 0, 't' => $now, 'w' => $window];
        $r['n']++;
        $data[$key] = $r;
    });
}

/* Zera o contador deste IP (ex.: login certo). */
function crew_rl_reset(string $bucket, ?string $ip = null, ?int $now = null, ?string $dir = null): void {
    $key = crew_rl_key($ip ?? crew_client_ip(), $dir);
    crew_rl_edit($bucket, $now ?? time(), $dir, function (array &$data) use ($key) {
        unset($data[$key]);
    });
}
