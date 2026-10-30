#!/usr/bin/env bash
# Testes de ponta a ponta: sobe o servidor embutido do PHP numa cópia temporária do site
# e confere os limites de login, contato, newsletter e lead e a área do cliente por empresa
# (páginas, ponte com a Plataforma, empresas separadas, escrita só por POST). A Plataforma é
# uma de mentira (tests/mock-plataforma.php); nenhum token ou dado real é usado.
# Rode: bash tests/http_test.sh
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
PORT="${PORT:-8765}"
MPORT="${MPORT:-8799}"
fails=0; total=0
cleanup() { for p in "${PID:-}" "${MPID:-}"; do [ -n "$p" ] && { kill "$p" 2>/dev/null; wait "$p" 2>/dev/null; }; done; rm -rf "$TMP"; }
trap cleanup EXIT

check() { total=$((total+1)); if [ "$2" != "$3" ]; then fails=$((fails+1)); echo "FALHOU: $1 (esperado '$3', veio '$2')"; fi; }

cp -R "$ROOT/assets" "$ROOT/.htaccess" "$ROOT/painel.php" "$ROOT/entregas.php" "$ROOT/plataformas.php" "$TMP/" 2>/dev/null
HASH="$(php -r 'echo password_hash("senha-certa", PASSWORD_BCRYPT);')"
cat > "$TMP/assets/php/users.php" <<PHP
<?php return [
  'teste@example.com' => ['hash' => '$HASH'],
  'a@example.com' => ['hash' => '$HASH', 'empresa' => 'EMP-0001'],
  'b@example.com' => ['hash' => '$HASH', 'empresa' => 'EMP-0002'],
  'c@example.com' => ['hash' => '$HASH', 'empresa' => 'EMP-0003'],
];
PHP
# Arquivo do token (fora do site, como no servidor) e Plataforma de mentira.
TOKEN='crewsite_testtoken0123456789'
printf "<?php return ['url' => 'http://127.0.0.1:%s', 'token' => '%s'];\n" "$MPORT" "$TOKEN" > "$TMP/crew-plataforma.php"
mkdir -p "$TMP/mock"
MOCK_DIR="$TMP/mock" php -S "127.0.0.1:$MPORT" "$ROOT/tests/mock-plataforma.php" >"$TMP/mock.log" 2>&1 &
MPID=$!
rm -rf "$TMP/assets/php/data"
CREW_PLATAFORMA_CONFIG="$TMP/crew-plataforma.php" php -d sendmail_path=/usr/bin/true -S "127.0.0.1:$PORT" -t "$TMP" >"$TMP/server.log" 2>&1 &
PID=$!
for i in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT/assets/php/login.php" && break; sleep 0.2; done
B="http://127.0.0.1:$PORT/assets/php"

code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

# --- login certo funciona e zera as falhas anteriores ---
for i in 1 2 3; do code -X POST -H 'Accept: application/json' -d 'email=teste@example.com&password=errada' "$B/login.php" >/dev/null; done
check "login: senha certa entra (200)" "$(code -X POST -H 'Accept: application/json' -d 'email=teste@example.com&password=senha-certa' "$B/login.php")" "200"

# --- login: 8 erros por IP, sem cookie e com X-Forwarded-For forjado ---
codes=""
for i in $(seq 1 9); do
  codes="$codes $(code -X POST -H 'Accept: application/json' -H "X-Forwarded-For: 9.9.9.$i" -d 'email=teste@example.com&password=errada' "$B/login.php")"
done
check "login: 8 erros 401 e o 9o 429 (sem cookie, XFF forjado)" "$codes" " 401 401 401 401 401 401 401 401 429"
check "login: senha certa tambem bloqueada durante o bloqueio" "$(code -X POST -H 'Accept: application/json' -d 'email=teste@example.com&password=senha-certa' "$B/login.php")" "429"
check "login: pasta de dados nao abre pelo navegador (arquivo .htaccess da pasta)" "$([ -f "$TMP/assets/php/data/.htaccess" ] && echo sim)" "sim"

# --- contato: 3 por minuto ---
contact() { curl -s -o /dev/null -w '%{redirect_url}' -X POST \
  --data-urlencode 'nome=Teste' --data-urlencode 'email=a@example.com' --data-urlencode 'empresa=Emp' \
  --data-urlencode 'investimento=ate-5k' --data-urlencode 'mensagem=oi' "$B/contact.php" | sed 's/.*status=//'; }
r=""; for i in 1 2 3 4; do r="$r $(contact)"; done
check "contato: 3 sucessos e o 4o bloqueado" "$r" " success success success error"

# --- newsletter: 5 por minuto ---
news() { code -X POST -H 'Accept: application/json' -d 'email=a@example.com' "$B/newsletter.php"; }
r=""; for i in 1 2 3 4 5 6; do r="$r $(news)"; done
check "newsletter: 5 ok e o 6o 429" "$r" " 200 200 200 200 200 429"
check "newsletter: corpo do bloqueio" "$(curl -s -X POST -H 'Accept: application/json' -d 'email=a@example.com' "$B/newsletter.php")" '{"status":"error"}'

# --- lead: 5 por minuto ---
LEAD='{"nome":"A","whatsapp":"19999999999","provedor":"P","cidade":"C","consentimento":true,"lead_score":"quente"}'
lead() { code -X POST -H 'Content-Type: application/json' -d "$LEAD" "$B/lead.php"; }
r=""; for i in 1 2 3 4 5 6; do r="$r $(lead)"; done
check "lead: 5 ok e o 6o 429" "$r" " 200 200 200 200 200 429"
check "lead: corpo do bloqueio" "$(curl -s -X POST -H 'Content-Type: application/json' -d "$LEAD" "$B/lead.php")" '{"ok":false}'

# ===================== área do cliente por empresa =====================
rm -f "$TMP"/assets/php/data/rl-login.json   # o teste de login acima deixou este IP bloqueado
A="$B/area/api.php"
M="http://127.0.0.1:$MPORT"
ORIGEM="http://127.0.0.1:$PORT"
mock_reset() { curl -s -o /dev/null -X POST "$M/__test/reset"; }
mock_n() { curl -s "$M/__test/log" | grep -c . ; }
mock_log() { curl -s "$M/__test/log"; }
entrar() { curl -s -o /dev/null -c "$TMP/jar-$1" -X POST -H 'Accept: application/json' -d "email=$1@example.com&password=senha-certa" "$B/login.php"; }
get_as() { curl -s -b "$TMP/jar-$1" "${@:2}"; }
code_as() { curl -s -o /dev/null -w '%{http_code}' -b "$TMP/jar-$1" "${@:2}"; }
# POST na ponte, como o navegador faria (cabeçalhos certos); $1 = usuário, $2 = corpo JSON, resto = cabeçalhos a mais
decidir_as() { curl -s -b "$TMP/jar-$1" -X POST -H "Origin: $ORIGEM" -H 'X-Requested-With: fetch' -H 'Content-Type: application/json' -d "$2" "${@:3}" "$A?acao=decidir"; }
decidir_code() { curl -s -o /dev/null -w '%{http_code}' -b "$TMP/jar-$1" -X POST -H "Origin: $ORIGEM" -H 'X-Requested-With: fetch' -H 'Content-Type: application/json' -d "$2" "${@:3}" "$A?acao=decidir"; }
for u in teste a b c; do entrar "$u"; done
mock_reset

# --- páginas sem login vão ao login; a ponte sem login dá 401 ---
for pg in painel.php entregas.php plataformas.php; do
  check "area: $pg sem login → 303 para o login" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "http://127.0.0.1:$PORT/$pg")" "303 http://127.0.0.1:$PORT/area-do-cliente.html"
done
check "area: ponte sem login → 401" "$(code -H 'X-Requested-With: fetch' "$A?acao=entregas")" "401"
check "area: ponte sem login (POST) → 401" "$(code -X POST -H "Origin: $ORIGEM" -H 'X-Requested-With: fetch' -d '{}' "$A?acao=decidir")" "401"

# --- includes e a pasta da ponte não abrem direto (só o api.php) ---
for f in layout.php plataforma.php; do
  r="$(curl -s -w '|%{http_code}' "$B/area/$f")"
  check "area: $f direto → 404 e sem conteúdo" "$r" "|404"
done
check "area: .htaccess bloqueia a pasta da ponte menos o api.php" "$(grep -c 'assets/php/area/(?!api' "$ROOT/.htaccess")" "1"

# --- cada empresa vê só o que é seu ---
mock_reset
ra="$(get_as a "$A?acao=entregas&empresa=EMP-0002")"
check "area: empresa A lista só as entregas de A (mesmo pedindo ?empresa=EMP-0002)" "$(echo "$ra" | jq -r '[.entregas[].id] | join(",")')" "tarefa-a1,tarefa-a2,tarefa-a3"
check "area: a Plataforma recebeu empresa=EMP-0001 (a da sessão)" "$(mock_log | jq -r '.uri' | head -1)" "/api/site/v1/entregas?empresa=EMP-0001"
check "area: o token foi com a chamada, e certo" "$(mock_log | jq -r '.token_ok' | head -1)" "true"
check "area: a resposta ao navegador não tem o token" "$(echo "$ra" | grep -c "$TOKEN")" "0"
check "area: cada entrega vem com acao_cliente" "$(echo "$ra" | jq -c '[.entregas[].acao_cliente]')" '["tema","arte",null]'
check "area: total esperando = 2" "$(echo "$ra" | jq '.esperando')" "2"
check "area: a lista traz as frentes e o link assinado" "$(echo "$ra" | jq -r '(.frentes|length|tostring) + " " + (.entregas[1].versao.link|startswith("https://")|tostring)')" "2 true"
rb="$(get_as b "$A?acao=entregas")"
check "area: empresa B lista só as de B" "$(echo "$rb" | jq -r '[.entregas[].id] | join(",")')" "tarefa-b1"
check "area: empresa B não tem nada de A na resposta" "$(echo "$rb" | grep -c 'empresa A')" "0"
mock_reset
check "area: usuário sem empresa → 404" "$(code_as teste "$A?acao=entregas")" "404"
check "area: usuário sem empresa não gerou chamada à Plataforma" "$(mock_n)" "0"
check "area: empresa que a Plataforma não conhece (EMP-0003) → 404 sem vazar nada" "$(get_as c "$A?acao=entregas")" '{"erro":"não encontrado"}'
check "area: cabeçalhos da ponte (no-store e nosniff)" "$(curl -s -D - -o /dev/null -b "$TMP/jar-a" "$A?acao=entregas" | tr -d '\r' | grep -ci -E '^(cache-control: .*no-store|x-content-type-options: nosniff)')" "2"
check "area: rota desconhecida → 404" "$(code_as a "$A?acao=qualquer")" "404"

# --- escrita só por POST, com cabeçalho e Origin certos ---
mock_reset
D='{"tarefa":"tarefa-a1","tipo":"tema","versao":1,"decisao":"aprovada","empresa":"EMP-0002","por":"falso@x.com"}'
check "area: GET com acao=decidir → 405" "$(code_as a "$A?acao=decidir&tarefa=tarefa-a1&tipo=tema&versao=1&decisao=aprovada")" "405"
check "area: PUT → 405" "$(code_as a -X PUT -H "Origin: $ORIGEM" -H 'X-Requested-With: fetch' -d "$D" "$A?acao=decidir")" "405"
check "area: nada chegou à Plataforma nessas tentativas" "$(mock_n)" "0"
check "area: POST sem X-Requested-With → 403" "$(code_as a -X POST -H "Origin: $ORIGEM" -H 'Content-Type: application/json' -d "$D" "$A?acao=decidir")" "403"
check "area: POST com Origin de outro site → 403" "$(code_as a -X POST -H 'Origin: https://site-do-mal.example' -H 'X-Requested-With: fetch' -d "$D" "$A?acao=decidir")" "403"
check "area: POST com Origin de outra porta → 403" "$(code_as a -X POST -H 'Origin: http://127.0.0.1:1' -H 'X-Requested-With: fetch' -d "$D" "$A?acao=decidir")" "403"
check "area: POST sem Origin → 403" "$(code_as a -X POST -H 'X-Requested-With: fetch' -d "$D" "$A?acao=decidir")" "403"
check "area: nenhuma dessas recusas chegou à Plataforma" "$(mock_n)" "0"
check "area: POST de usuário sem empresa → 404 e nada chega à Plataforma" "$(decidir_code teste "$D")$(mock_n)" "4040"
check "area: corpo inválido (não é JSON) → 400" "$(decidir_code a 'isso não é json')" "400"
check "area: ajuste sem motivo nem sugestão → 400, sem chamar a Plataforma" "$(decidir_code a '{"tarefa":"tarefa-a1","tipo":"tema","versao":1,"decisao":"ajuste"}')$(mock_n)" "4000"
r="$(decidir_as a "$D")"
check "area: POST certo → 200 com a entrega atualizada" "$(echo "$r" | jq -r '.entrega.tema_estado + " " + (.repetida|tostring)')" "aprovado false"
ult="$(mock_log | tail -1)"
check "area: a Plataforma recebeu a empresa da sessão (não a do corpo)" "$(echo "$ult" | jq -r '.corpo.empresa')" "EMP-0001"
check "area: a Plataforma recebeu o e-mail do login em 'por'" "$(echo "$ult" | jq -r '.corpo.por')" "a@example.com"
check "area: 'em' veio do servidor do site (ISO 8601 de hoje)" "$(echo "$ult" | jq -r '.corpo.em | startswith("'"$(date -u +%Y-%m-%d)"'")')" "true"
check "area: a resposta ao navegador não tem o token nem o e-mail" "$(echo "$r" | grep -c -e "$TOKEN" -e 'a@example.com')" "0"
check "area: decisão repetida → 200 repetida:true" "$(decidir_as a "$D" | jq -r '.repetida')" "true"
check "area: decisão diferente da primeira → 409 ja_decidida repassado" "$(decidir_as a '{"tarefa":"tarefa-a1","tipo":"tema","versao":1,"decisao":"ajuste","motivos":["x"]}' | jq -r '.codigo')" "ja_decidida"
check "area: 409 repassa com o status 409" "$(decidir_code a '{"tarefa":"tarefa-a1","tipo":"tema","versao":1,"decisao":"ajuste","motivos":["x"]}')" "409"
check "area: empresa B decidindo numa tarefa de A → 404" "$(decidir_code b '{"tarefa":"tarefa-a2","tipo":"arte","versao":1,"decisao":"aprovada"}')" "404"
check "area: a tarefa de A seguiu intacta depois da tentativa de B" "$(get_as a "$A?acao=entregas" | jq -r '.entregas[1].coluna')" "com_cliente"

# --- fluxo completo: tema → arte → pedir ajuste → versão nova → aprovar ---
mock_reset
curl -s -o /dev/null -X POST -d '{"tarefa":"tarefa-a1","acao":"enviar_arte"}' "$M/__test/avancar"
check "fluxo: com a arte enviada, o tema pendente ainda vem primeiro" "$(get_as a "$A?acao=entregas" | jq -r '.entregas[0].acao_cliente')" "tema"
decidir_as a '{"tarefa":"tarefa-a1","tipo":"tema","versao":1,"decisao":"aprovada"}' >/dev/null
check "fluxo: depois de aprovar o tema a arte espera o cliente" "$(get_as a "$A?acao=entregas" | jq -r '.entregas[0].acao_cliente')" "arte"
check "fluxo: pedir ajuste na arte → coluna ajuste, 1 rodada" "$(decidir_as a '{"tarefa":"tarefa-a1","tipo":"arte","versao":1,"decisao":"ajuste","motivos":["Cores ou visual"],"sugestao":"Mais contraste"}' | jq -r '.entrega.coluna + " " + (.entrega.rodadas_arte|tostring)')" "ajuste 1"
curl -s -o /dev/null -X POST -d '{"tarefa":"tarefa-a1","acao":"nova_versao"}' "$M/__test/avancar"
check "fluxo: versão nova (2) volta a esperar o cliente" "$(get_as a "$A?acao=entregas" | jq -r '(.entregas[0].versao.numero|tostring) + " " + .entregas[0].acao_cliente')" "2 arte"
check "fluxo: aprovar a versão 1, que já teve ajuste pedido → 409 ja_decidida" "$(decidir_as a '{"tarefa":"tarefa-a1","tipo":"arte","versao":1,"decisao":"aprovada"}' | jq -r '.codigo')" "ja_decidida"
check "fluxo: aprovar a versão nova → entregue" "$(decidir_as a '{"tarefa":"tarefa-a1","tipo":"arte","versao":2,"decisao":"aprovada"}' | jq -r '.entrega.coluna')" "entregue"
check "fluxo: depois de entregue não espera mais ninguém" "$(get_as a "$A?acao=entregas" | jq -r '.entregas[0].acao_cliente')" "null"
mock_reset
nova_versao() { curl -s -o /dev/null -X POST -d "{\"tarefa\":\"$1\",\"acao\":\"nova_versao\"}" "$M/__test/avancar"; }
ajuste_arte() { decidir_as a "{\"tarefa\":\"tarefa-a2\",\"tipo\":\"arte\",\"versao\":$1,\"decisao\":\"ajuste\",\"motivos\":[\"Texto da arte\"]}"; }
ajuste_arte 1 >/dev/null; nova_versao tarefa-a2
ajuste_arte 2 >/dev/null; nova_versao tarefa-a2
check "fluxo: 3º pedido de ajuste → 409 limite_de_ajustes repassado" "$(ajuste_arte 3 | jq -r '.codigo')" "limite_de_ajustes"
check "fluxo: depois do limite só aprovar continua valendo" "$(decidir_as a '{"tarefa":"tarefa-a2","tipo":"arte","versao":3,"decisao":"aprovada"}' | jq -r '.entrega.coluna')" "entregue"

# --- Plataforma com problema → aviso genérico, sem vazar nada ---
mock_reset
echo 500 > "$TMP/mock/falha"
r="$(curl -s -w '|%{http_code}' -b "$TMP/jar-a" "$A?acao=entregas")"
check "area: Plataforma devolve 500 → 502 genérico" "$r" '{"erro":"indisponível"}|502'
check "area: decisão com a Plataforma em 500 → 502" "$(decidir_code a "$D")" "502"
echo 401 > "$TMP/mock/falha"
check "area: Plataforma devolve 401 (token ruim) → 502 para o navegador" "$(code_as a "$A?acao=entregas")" "502"
rm -f "$TMP/mock/falha"
printf "<?php return ['url' => 'http://127.0.0.1:1', 'token' => '%s'];\n" "$TOKEN" > "$TMP/crew-plataforma.php"
r="$(curl -s -w '|%{http_code}' -b "$TMP/jar-a" "$A?acao=entregas")"
check "area: Plataforma fora do ar (conexão recusada) → 502 genérico, sem caminho nem token" "$r" '{"erro":"indisponível"}|502'
mv "$TMP/crew-plataforma.php" "$TMP/crew-plataforma.off"
r="$(curl -s -w '|%{http_code}' -b "$TMP/jar-a" "$A?acao=entregas")"
check "area: arquivo do token ausente → 502 genérico, sem vazar o caminho" "$r" '{"erro":"indisponível"}|502'
printf "<?php return ['url' => 'http://127.0.0.1:%s', 'token' => '%s'];\n" "$MPORT" "$TOKEN" > "$TMP/crew-plataforma.php"
rm -f "$TMP/crew-plataforma.off"
check "area: com o arquivo de volta, a ponte volta a funcionar" "$(code_as a "$A?acao=entregas")" "200"

# --- páginas com login: o menu depende da empresa ---
pa="$(get_as a "http://127.0.0.1:$PORT/entregas.php")"
check "pagina: entregas.php de quem tem empresa abre (200)" "$(code_as a "http://127.0.0.1:$PORT/entregas.php")" "200"
check "pagina: o menu de quem tem empresa tem Entregas e Plataformas" "$(echo "$pa" | grep -c -e 'href="entregas.php"' -e 'href="plataformas.php"')" "2"
check "pagina: Relatórios e Produtos aparecem como 'em breve', sem link" "$(echo "$pa" | grep -c -e 'relatorios.php' -e 'produtos.php')$(echo "$pa" | grep -o 'data-i18n="area.nav.soon">em breve' | wc -l | tr -d ' ')" "02"
check "pagina: o menu tem Sair (logout)" "$(echo "$pa" | grep -c 'assets/php/logout.php')" "1"
check "pagina: painel.php (Início) abre para quem tem empresa" "$(code_as a "http://127.0.0.1:$PORT/painel.php")" "200"
check "pagina: o token não aparece em nenhuma página" "$(echo "$pa" "$(get_as a "http://127.0.0.1:$PORT/painel.php")" | grep -c "$TOKEN")" "0"
pt="$(get_as teste "http://127.0.0.1:$PORT/plataformas.php")"
check "pagina: sem empresa, plataformas.php abre" "$(code_as teste "http://127.0.0.1:$PORT/plataformas.php")" "200"
check "pagina: sem empresa, o menu não tem Entregas" "$(echo "$pt" | grep -c 'href="entregas.php"')" "0"
check "pagina: sem empresa, painel.php vai para plataformas.php" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$TMP/jar-teste" "http://127.0.0.1:$PORT/painel.php")" "303 http://127.0.0.1:$PORT/plataformas.php"
check "pagina: sem empresa, entregas.php vai para plataformas.php" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$TMP/jar-teste" "http://127.0.0.1:$PORT/entregas.php")" "303 http://127.0.0.1:$PORT/plataformas.php"
check "pagina: as plataformas continuam com os 3 cartões" "$(echo "$pt" | grep -o -e 'SPEAKR.CREW' -e 'GAMING.CREW' -e 'RULLER.CREW' | sort -u | wc -l | tr -d ' ')" "3"

# --- limite de uso: por usuário logado e por IP (o IP real atrás da CDN ainda não foi confirmado) ---
rm -f "$TMP"/assets/php/data/rl-area*.json
mock_reset
r=""; for i in $(seq 1 61); do r="$r $(code_as a "$A?acao=entregas")"; done
check "limite: usuário A, 60 leituras ok e a 61ª → 429" "$(echo "$r" | tr ' ' '\n' | grep -c '^200$')/$(echo "$r" | tr ' ' '\n' | grep -c '^429$')" "60/1"
check "limite: outro usuário no mesmo IP continua com o limite dele (200)" "$(code_as b "$A?acao=entregas")" "200"
check "limite: o 429 traz Retry-After" "$(curl -s -D - -o /dev/null -b "$TMP/jar-a" "$A?acao=entregas" | tr -d '\r' | grep -ci '^retry-after:')" "1"
r=""; for i in $(seq 1 21); do r="$r $(decidir_code b '{"tarefa":"tarefa-b1","tipo":"arte","versao":1,"decisao":"aprovada"}')"; done
check "limite: usuário B, 20 decisões ok e a 21ª → 429" "$(echo "$r" | tr ' ' '\n' | grep -c '^200$')/$(echo "$r" | tr ' ' '\n' | grep -c '^429$')" "20/1"
check "limite: usuário A ainda pode decidir (limite próprio, mesmo IP)" "$(decidir_code a '{"tarefa":"tarefa-a1","tipo":"tema","versao":1,"decisao":"aprovada"}')" "200"
check "limite: o e-mail do login não aparece nos contadores" "$(grep -rl -e 'a@example.com' -e 'b@example.com' "$TMP/assets/php/data" 2>/dev/null | wc -l | tr -d ' ')" "0"
check "limite: o limite por usuário e o por IP existem (dois contadores por rota)" "$(ls "$TMP"/assets/php/data | grep -c '^rl-area')" "4"

echo "$([ $fails -eq 0 ] && echo OK || echo FALHAS): $((total-fails)) de $total verificacoes passaram"
[ $fails -eq 0 ]
