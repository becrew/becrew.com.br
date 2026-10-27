# Plano: nova área do cliente, fase 1 (lado do site)

Status: **aprovado por Rafael em 2026-10-26, com uma mudança (limite de uso por usuário logado e por IP, escolha 5). Parecer do Co-CEO conferido. Código em andamento.**
Escopo: menu, Início, Entregas, Plataformas e a ponte com a API da Plataforma. Relatórios, Produtos e pontuação (fases 2 a 4) ficam de fora.

## O que entendi
- **Objetivo:** o cliente logado vê, numa área só, as entregas de todas as frentes em que a empresa dele é cliente, aprova o tema, aprova a arte ou pede ajuste, e baixa o arquivo. O site não guarda nada: tudo vive na Plataforma, e o site lê e envia as decisões por API.
- **Restrições:** o token do site nunca chega ao navegador nem ao repositório; a empresa vem sempre da sessão; escrita só por POST confirmado; nada interno no repositório público; a `main` publica por FTP a cada push, então tudo vai em branch e pull request (não junto).
- **Pronto quando:** testes verdes, fluxo completo no navegador (aprovar tema, pedir ajuste na arte, aprovar a versão nova) e um pull request aberto em cada repositório.
- **Suposição minha:** o contrato da API é o que está em `docs/INTEGRACOES.md` e `docs/SITE-ENTREGAS.md` da Plataforma (já no ar). Não vou tocar na Plataforma.

## Onde vai o código
Quase tudo vai no repositório **público** (`becrew.com.br`): são páginas, include, ponte e testes, nenhum segredo. O **privado** só ganha a ferramenta de criar conta e a documentação com os caminhos reais do servidor.

### Repositório público (`becrew.com.br`), branch `area-cliente-fase1`
| Arquivo | O quê |
|---|---|
| `assets/php/auth.php` | **muda:** nova `crew_current_profile()` devolve `['email', 'empresa']` do usuário logado (empresa só se tiver o formato `EMP-0000`). `crew_current_user()` continua igual (devolve o e-mail), para o login não quebrar |
| `assets/php/area/layout.php` | **novo:** topo, barra lateral, painel do menu do site e o **menu da área** (Início · Entregas · Relatórios · Produtos · Plataformas · Sair). Saem do `painel-cliente.html` sem mudar o visual. Funções `area_abrir()` e `area_fechar()`, e a porta de login comum `area_exigir_login()` |
| `assets/php/area/plataforma.php` | **novo:** cliente da API (cURL, timeout 8 s, TLS verificado), leitura do token, validação da decisão e a regra "o que espera o cliente" |
| `assets/php/area/api.php` | **novo:** a ponte única (abaixo) |
| `painel.php` | **vira o Início:** porta de login, usa o layout, mostra quantas entregas esperam o cliente (busca pela ponte) |
| `entregas.php` | **novo:** porta de login + layout + a tela de entregas |
| `plataformas.php` | **novo:** porta de login + layout + os três cartões de hoje (Speakr, Gaming, Ruller), sem mudança de comportamento |
| `painel-cliente.html` | **removido:** o conteúdo vai para o layout e para `plataformas.php` |
| `assets/js/crew-entregas.js`, `assets/css/crew-entregas.css` | **novos:** a interface de Entregas, vinda do `loopy/page.html` sem a parte da Crew, com o CSS limitado à classe da página (não leva o CSS global do Loopy) |
| `assets/js/crew-area.js` | **novo:** pedaço comum (chamada à ponte, contador do Início, aviso de erro) |
| `assets/js/lang/en/area.js` | **novo:** textos em inglês do menu, Início e Entregas (PT fica no HTML; textos de JS via `crewT()`) |
| `assets/js/lucide-local.js` | **muda:** só os ícones novos que faltarem (a conferir: `calendar`, `download`, `clock`, `home`, `file-text`, `package`, `alert-circle`) |
| `assets/php/users.example.php` | **muda:** modelo com `'empresa' => 'EMP-0000'` e a explicação |
| `.htaccess` | **muda:** bloqueia `assets/php/area/` inteira **exceto** `api.php` (e o `painel-cliente.html` antigo continua bloqueado, caso fique no servidor) |
| `.github/workflows/deploy.yml` | **muda:** passa a excluir `docs/**` (o plano e os docs não vão para o servidor) |
| `README.md` | **muda:** como a integração funciona, o que fica só no servidor, os testes |
| `docs/PLANO-AREA-FASE1.md` | este arquivo |
| `tests/http_test.sh` | **amplia** (ver Testes); `tests/area_test.php` e `tests/mock-plataforma.php` **novos** |

### Repositório privado (`becrew.com.br-privado`), branch `area-cliente-fase1`
| Arquivo | O quê |
|---|---|
| `ferramentas/criar-conta.php` | **muda:** aceita `--empresa EMP-0000` (também `--empresa=EMP-0000`); valida o formato; imprime a linha com `'empresa' => '...'`. Sem `--empresa` funciona como hoje, mas avisa que o usuário só verá Plataformas |
| `tests/criar_conta_test.php` | **amplia** (ver Testes) |
| `README.md` | **muda:** passo da empresa na criação de conta e o **caminho completo do arquivo do token no servidor** (que não vai no repositório público) |
| `loopy/**` | **não muda.** A Loopy continua no ar como está |
| `assets/php/users.php` | **não toco.** Quem põe `empresa` nos usuários reais é Rafael, com a linha que o `criar-conta.php` imprime |

## O que fica só no servidor (nada disso entra em repositório)
- **`crew-plataforma.php`**, um nível acima de `public_html`, com `url` e `token`. Rafael grava e sobe por conta própria (hPanel).
- **`assets/php/users.php`** real, agora com `empresa` em cada usuário (já era só no servidor).
- `assets/php/data/` (contadores do limite), como hoje.
- O código novo em si (`api.php`, layout, páginas) vai pelo deploy normal: não tem segredo.

## A ponte (`assets/php/area/api.php`)
**Rotas** (tudo em JSON, com `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`, `X-Robots-Tag: noindex`):
- `GET api.php?acao=entregas` → chama `GET /api/site/v1/entregas?empresa=<da sessão>` e devolve a lista. A ponte acrescenta em cada entrega `acao_cliente` (`"tema"`, `"arte"` ou `null`), calculada pela tabela de `docs/SITE-ENTREGAS.md`, e um total `esperando`. Assim o Início e a tela de Entregas usam a mesma regra.
- `POST api.php?acao=decidir` → corpo JSON `{tarefa, tipo, versao, decisao, motivos, sugestao}`; chama `POST /api/site/v1/decisoes`. 200 (inclusive `repetida: true`) e 409 passam ao navegador como vieram, para a tela redesenhar ou mostrar a mensagem. 404 e 400 viram erro genérico. Qualquer outra falha (401, 429, 5xx, tempo esgotado, config ausente) vira **502** `{"erro":"indisponível"}`.
- Qualquer outra rota ou método → 405/404. **GET nunca escreve.**

**Ordem das verificações:** sessão (401 sem login) → empresa válida na sessão (404 sem empresa) → método e rota → limite de uso → (POST) `X-Requested-With: fetch`, **`Origin` presente e com o mesmo host (e porta) do site, senão 403** → corpo → chamada à Plataforma.

**Regras de segurança**
- A `empresa` vem só de `crew_current_profile()`; qualquer `empresa` no corpo ou na URL é ignorada. O `por` é o e-mail do login; o `em` é `gmdate('c')` do servidor do site. Nenhum dos dois vem do navegador.
- Só os campos conhecidos passam, com tamanhos limpos (motivos: até 8 de 80 caracteres; sugestão: até 1000; corpo: até 16 KB). `ajuste` sem motivo e sem sugestão é recusado já na ponte.
- Fecha a sessão (`session_write_close()`) antes de chamar a Plataforma, para uma chamada lenta não travar as outras abas.
- Limite de uso **por usuário logado e por IP**, com o `ratelimit.php` que já existe: **leitura 60/min, decisão 20/min** para cada um dos dois; passou de qualquer um, 429. A chave do usuário é o hash (com o sal do `ratelimit.php`) do e-mail do login: o e-mail nunca aparece no nome nem no conteúdo do arquivo de contadores. Motivo: o IP real do visitante atrás da CDN da Hostinger ainda não foi confirmado; se o servidor enxergar o IP da CDN, só o limite por IP faria todos os clientes dividirem um limite. A Plataforma aceita 300/min no total.
- Log só com status e código da empresa. Nunca e-mail, texto do cliente, token nem conteúdo da entrega.

**Como a ponte lê o token:** `require` do arquivo `crew-plataforma.php` um nível acima de `public_html` (`dirname(__DIR__, 4)` a partir de `assets/php/area/`). Ele devolve `['url' => ..., 'token' => ...]`. A ponte confere que `url` é `https://` e que o `token` começa com `crewsite_`, e manda `Authorization: Bearer` só nessa chamada. O token nunca vai ao navegador, ao HTML, a log nem a mensagem de erro. Para teste local, a variável de ambiente `CREW_PLATAFORMA_CONFIG` aponta para outro arquivo (e só nesse caso aceita `http://127.0.0.1`); no servidor ela não existe.

## As páginas
- **Porta de login:** toda página começa com `area_exigir_login()`: sem `crew_current_user()`, 303 para `area-do-cliente.html`. Cabeçalhos `no-store` e `noindex`, como o `painel.php` de hoje.
- **Usuário sem empresa:** vê só Plataformas. O menu mostra só "Plataformas" e "Sair"; `painel.php` (Início) e `entregas.php` redirecionam para `plataformas.php`; a ponte responde 404. O login continua mandando para `painel.php`, então quem não tem empresa cai em Plataformas, que é o que ele vê hoje.
- **Início:** título, uma frase e o número "N entregas esperando você" com botão para Entregas; "nenhuma entrega esperando" quando zero; "não foi possível carregar, tente de novo" se a ponte falhar.
- **Entregas:** cartão por entrega, com a **frente** (nome vindo de `frentes`), título, produto, data (`postar_em`, "Agendado para" em horário de São Paulo, só mostra; senão `data_planejada`), indicador das etapas (tema, arte, aprovação) e, conforme o estado, a ação (tabela de `SITE-ENTREGAS.md`): aprovar ou pedir ajuste do tema (motivos e sugestão), arte para baixar com aprovar ou pedir ajuste, "aprove o tema antes", "a Crew está ajustando", "em produção", "entregue" (com "aprovada automaticamente" se `ultima_decisao.decisao = "tacita"`). `tacita_ate` só aparece se vier preenchido. Aviso quando `rodadas_*` chega a 2 ("o contrato prevê 2 rodadas"). 409: mostra a `mensagem` e recarrega. Filtros: por situação (esperando você, com ajuste, em produção, entregues) e por frente. **Sai o que é só da Crew:** subir arquivo, agendar, desagendar, editar conteúdo, resumo para WhatsApp, tabelas de rotina e datas.
- **Plataformas:** os três cartões de hoje, como estão.
- **Menu:** faixa de abas no cabeçalho da página, com a aba atual marcada. **Relatórios e Produtos** aparecem desligados, com a etiqueta "em breve" (sem página nem link). O menu do site (hambúrguer) não muda.

## Testes (escritos antes do código; vermelhos primeiro, depois verdes)
**`tests/http_test.sh`** (servidor `php -S` numa cópia temporária + `tests/mock-plataforma.php` como Plataforma de mentira, que confere o token, guarda o que recebeu e imita rodadas, idempotência e o 409). As credenciais de teste são valores fixos do próprio script, só em `127.0.0.1`:
1. `painel.php`, `entregas.php`, `plataformas.php` sem login → 303 para o login; a ponte sem login → 401.
2. Include e pasta da ponte acessados direto (`layout.php`, `plataforma.php`) → 404 e sem conteúdo.
3. Usuário da empresa A: lista só os itens de A; a Plataforma de mentira recebeu `empresa=A` mesmo com `?empresa=B` na URL.
4. Usuário da empresa B não vê nada de A (e o contrário); usuário **sem empresa** → 404 e a Plataforma de mentira não recebeu chamada nenhuma.
5. Ponte recusa **GET que escreve** (405, nada chegou à Plataforma), POST sem `X-Requested-With` (403), POST com `Origin` de outro site (403) e POST sem `Origin` (403).
6. POST válido: a Plataforma recebeu `empresa` da sessão (ignorando a do corpo), `por` = e-mail do login e `Authorization` correto; a resposta ao navegador não contém o token.
7. Decisão numa tarefa de outra empresa → 404; decisão repetida → 200 `repetida`; versão velha → 409 repassado; 3º pedido de ajuste → 409 `limite_de_ajustes` repassado.
8. Plataforma fora do ar, 500 ou config ausente → 502 genérico, sem vazar caminho nem token.
9. Limite de uso: passado o limite, 429; dois usuários diferentes **do mesmo IP** têm cada um o seu limite (um estoura e leva 429, o outro continua com 200); o contador não guarda o e-mail em claro.
10. Páginas logadas: usuário com empresa vê Entregas no menu; usuário sem empresa só vê Plataformas, e `painel.php`/`entregas.php` o redirecionam.
11. `.htaccess` tem a regra de bloqueio da pasta da ponte com a exceção do `api.php` (conferência de texto, porque o servidor do PHP não lê `.htaccess`; o bloqueio real eu confiro com `curl` no ar depois do merge).

**`tests/area_test.php`** (PHP puro): formato de `empresa`, validação e limpeza da decisão (campos, tamanhos, `ajuste` sem motivo), regra `acao_cliente` para cada linha da tabela do `SITE-ENTREGAS.md`, leitura do token (arquivo ausente, URL sem https, token sem prefixo).

**`privado/tests/criar_conta_test.php`:** `--empresa EMP-0007` imprime a linha com `empresa`, que carrega como o site carrega; as duas grafias (`--empresa X` e `--empresa=X`); formato inválido (`EMP-7`, `abc`) sai com erro e sem linha; sem `--empresa` a linha continua igual à de hoje e avisa; a senha não aparece na tela com `--senha`.

**De ponta a ponta, no navegador do app** (`php -S` + o mesmo mock; **não uso a Plataforma de verdade nem token real**): login → Início com o número → Entregas → aprovar o tema → pedir ajuste na arte (com motivo) → o mock sobe a versão nova → aprovar → vira "entregue". Também: segundo usuário de outra empresa vê lista vazia; falha da Plataforma mostra o aviso e nada muda.

## Escolhas que fiz por padrão (me corrijam o que não servir)
1. **Menu da área como abas no cabeçalho da página**, e não dentro do painel hambúrguer do site (esse continua o do site todo). É a mudança que menos mexe no resto.
2. **Relatórios e Produtos:** abas desligadas com "em breve", sem página.
3. **`painel-cliente.html` sai:** o conteúdo vira layout + `plataformas.php`. Um arquivo a menos para manter.
4. **Origin obrigatório** (mais rígido que o exemplo da Plataforma e que a Loopy, que aceitam sem Origin), comparado por host e porta, sem exigir `https` (para testar em `localhost`).
5. **Limites por usuário logado e por IP** (mudança aprovada por Rafael): 60 leituras e 20 decisões por minuto, para cada um.
6. **Atualização da tela:** busca de novo a cada 60 s só com a aba visível e ao voltar para a aba; nunca depois de uma decisão (usa a resposta dela).
7. **Textos de motivo genéricos** ("Tema não combina com a minha marca"), já que a Loopy tinha textos do cliente dela.
8. **Cada entrega aparece uma vez**, ordenada da mais antiga para a mais nova como a API entrega, agrupada por semana; sem data vai para "Sem data".
9. **Link de download** só é usado se for `https://`; nunca fica em cache nem em favorito.
10. **Sem a chave `frente` no `users.php`** (decisão do pedido: só `empresa`); a API aceitaria, mas o site não oferece.
11. **Branches `area-cliente-fase1` nos dois repositórios**, push e pull request só depois de tudo verde. Não faço merge.

## Passos e commits (pequenos, cada um com prova)
1. Testes vermelhos: `criar_conta_test.php` (privado) e `area_test.php` + `mock-plataforma.php` + `http_test.sh` (público).
2. `criar-conta.php --empresa` (privado) e README → verde.
3. `auth.php`, `plataforma.php` e `api.php` → testes da ponte verdes.
4. Layout, `painel.php`, `plataformas.php`, `.htaccess` → testes de páginas verdes.
5. Entregas (JS, CSS, ícones, EN).
6. README, `deploy.yml`, `users.example.php`.
7. Prova de ponta a ponta no navegador; suíte inteira de novo; pull requests com resumo.

## O que depende de Rafael depois do merge
Gravar `crew-plataforma.php` no servidor; subir o `users.php` com `empresa`; juntar os dois pull requests; conferir com `curl` e entrar com a conta dele; revisão de segurança antes do primeiro cliente real (regra da Plataforma).
