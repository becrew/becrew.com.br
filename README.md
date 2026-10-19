# becrew.com.br

Código-fonte do site da **.crew**, agência de marketing para provedores de internet: [becrew.com.br](https://becrew.com.br)

Site estático (HTML, CSS e JavaScript), com alguns scripts PHP para formulários e para a área do cliente. Hospedado na Hostinger.

## Estrutura

| Caminho | O que é |
|---|---|
| `index.html`, `sobre.html`, `servicos.html`, `comunidades.html`, `blog.html`, `contato.html` | Páginas principais |
| `blog/` | Posts do blog |
| `marketing-para-provedores-de-internet/` | Landing page do Raio X da Operação |
| `area-do-cliente.html`, `painel.php`, `painel-cliente.html` | Área do cliente (login) |
| `assets/css`, `assets/js`, `assets/img` | Estilos, scripts e imagens |
| `assets/js/lang/en/` | Traduções em inglês |
| `assets/php/` | Formulários (contato, newsletter, leads) e login |

## Como publicar

Cada push na branch `main` publica o site automaticamente no Hostinger pelo workflow [`deploy.yml`](.github/workflows/deploy.yml), que envia só os arquivos alterados por FTP.

A `main` é protegida: toda mudança entra por Pull Request e precisa de aprovação antes de ir para o ar.

## O que não está aqui

Por segurança, alguns arquivos ficam só no servidor:

- `assets/php/users.php`: usuários da área do cliente. Use [`users.example.php`](assets/php/users.example.php) como modelo.
- `loopy/`: área privada de um cliente.

## Uso

O código está aberto para consulta. Textos, imagens, logotipo e a marca .crew pertencem à Crew e não podem ser reutilizados sem autorização.
