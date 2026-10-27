# becrew.com.br

Código-fonte do site da **.crew**, agência de marketing para provedores de internet: [becrew.com.br](https://becrew.com.br)

Site estático (HTML, CSS e JavaScript), com alguns scripts PHP para formulários e para a área do cliente. Hospedado na Hostinger.

## Estrutura

| Caminho | O que é |
|---|---|
| `index.html`, `sobre.html`, `servicos.html`, `comunidades.html`, `blog.html`, `contato.html` | Páginas principais |
| `blog/` | Posts do blog |
| `marketing-para-provedores-de-internet/` | Landing page do Raio X da Operação |
| `area-do-cliente.html` | Login da área do cliente |
| `painel.php` (Início), `entregas.php`, `plataformas.php` | Páginas da área do cliente (exigem login; o menu e o topo ficam em `assets/php/area/layout.php`) |
| `assets/php/area/` | Ponte da área do cliente: `api.php` fala com a Plataforma da .crew; `plataforma.php` e `layout.php` são includes (bloqueados pelo `.htaccess`, só `api.php` responde) |
| `docs/` | Planos e desenhos (não vão para o servidor) |
| `assets/css`, `assets/js`, `assets/img` | Estilos, scripts e imagens |
| `assets/js/lang/en/` | Traduções em inglês |
| `assets/php/` | Formulários (contato, newsletter, leads) e login |

## Como publicar

Cada push na branch `main` publica o site automaticamente no Hostinger pelo workflow [`deploy.yml`](.github/workflows/deploy.yml), que envia só os arquivos alterados por FTP.

A `main` é protegida: toda mudança entra por Pull Request e precisa de aprovação antes de ir para o ar.

## Área do cliente: integração com a Plataforma

O site é só a vitrine: nenhuma entrega nem decisão é guardada aqui. Cada login pertence a **uma empresa** (`'empresa' => 'EMP-0000'` no `users.php`) e vê as entregas de todas as frentes em que ela é cliente. Quem não tem `empresa` vê só "Plataformas".

- O navegador fala só com `assets/php/area/api.php` (`GET ?acao=entregas`, `POST ?acao=decidir`). A ponte lê a empresa **da sessão**, nunca do navegador, e chama `crm.becrew.com.br` com um token que fica fora do `public_html`.
- Escrita só por POST, com `X-Requested-With: fetch` e `Origin` igual ao do site. Limite de 60 leituras e 20 decisões por minuto, por usuário logado (hash do e-mail) e por IP.
- Se a Plataforma não responder, a tela mostra "não foi possível carregar, tente de novo" e nada é gravado.
- Contrato da API e desenho: ficam no repositório da Plataforma (`docs/INTEGRACOES.md` e `docs/SITE-ENTREGAS.md`).

## O que não está aqui

Por segurança, alguns arquivos ficam só no servidor:

- `assets/php/users.php`: usuários da área do cliente, cada um com a `empresa`. Use [`users.example.php`](assets/php/users.example.php) como modelo. O privado tem `ferramentas/criar-conta.php --empresa EMP-0000` para gerar a linha.
- `crew-plataforma.php`, **um nível acima do `public_html`**, permissão 600, com `<?php return ['url' => 'https://crm.becrew.com.br', 'token' => 'crewsite_...'];`. O token é gerado na tela Sistemas da Plataforma e nunca entra em git, HTML, JS nem chat.
- `assets/php/data/`: contadores do limite de tentativas por IP (criada pelo próprio PHP).
- `loopy/`: área privada de um cliente.

## Testes

Precisam de PHP e `jq` no computador (a Plataforma de mentira de `tests/mock-plataforma.php` é subida pelo próprio teste):

```
php tests/ratelimit_test.php
php tests/area_test.php
bash tests/http_test.sh
```

## Uso

O código está aberto para consulta. Textos, imagens, logotipo e a marca .crew pertencem à Crew e não podem ser reutilizados sem autorização.
