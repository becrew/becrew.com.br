#!/usr/bin/env bash
# Testes de ponta a ponta: sobe o servidor embutido do PHP numa cópia temporária do site
# e confere os limites de login, contato, newsletter e lead.
# Rode: bash tests/http_test.sh
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
PORT="${PORT:-8765}"
fails=0; total=0
cleanup() { [ -n "${PID:-}" ] && { kill "$PID" 2>/dev/null; wait "$PID" 2>/dev/null; }; rm -rf "$TMP"; }
trap cleanup EXIT

check() { total=$((total+1)); if [ "$2" != "$3" ]; then fails=$((fails+1)); echo "FALHOU: $1 (esperado '$3', veio '$2')"; fi; }

cp -R "$ROOT/assets" "$ROOT/.htaccess" "$ROOT/painel.php" "$TMP/" 2>/dev/null
HASH="$(php -r 'echo password_hash("senha-certa", PASSWORD_BCRYPT);')"
cat > "$TMP/assets/php/users.php" <<PHP
<?php return ['teste@example.com' => ['hash' => '$HASH']];
PHP
rm -rf "$TMP/assets/php/data"
php -d sendmail_path=/usr/bin/true -S "127.0.0.1:$PORT" -t "$TMP" >"$TMP/server.log" 2>&1 &
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

echo "$([ $fails -eq 0 ] && echo OK || echo FALHAS): $((total-fails)) de $total verificacoes passaram"
[ $fails -eq 0 ]
