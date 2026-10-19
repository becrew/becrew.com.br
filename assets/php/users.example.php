<?php
/**
 * Crew - modelo do arquivo de usuários da área do cliente.
 * O arquivo real (users.php) fica só no servidor e não vai para o repositório público.
 * Copie este arquivo para users.php e troque pelo e-mail e hash reais.
 * Para gerar um hash: php -r "echo password_hash('SENHA', PASSWORD_DEFAULT);"
 */
if (!defined('CREW_AUTH')) { http_response_code(404); exit; }

return [
    'cliente@exemplo.com.br' => [
        'hash' => '$2y$10$COLE_AQUI_O_HASH_GERADO',
    ],
];
