<?php
/**
 * Crew - catálogo de produtos da área do cliente e a pontuação (XP) que vem deles.
 * A empresa soma pontos de experiência; os pontos definem o nível. Benefícios por nível virão depois.
 * Usado pelo layout (pontos na barra de cima e na lateral), pelo Início e pela vitrine de Produtos.
 */
if (!defined('CREW_AUTH')) { http_response_code(404); exit; }

/*
 * Etapa 1 dos pontos (decisão de Rafael, 2026-10-30): os níveis ficam à vista, mas o placar ainda não conta.
 * CREW_AREA_PLACAR: XP da empresa, nível atual, barra, XP de cada produto e limites dos níveis. Liga quando os
 *   pontos vierem da Plataforma, por empresa; a conta abaixo (crew_area_xp) ainda é a provisória do catálogo.
 *   Regra aprovada (Rafael, 2026-10-30): pontos = lucro bruto que a empresa gerou para a Crew, acumulado mês a mês.
 * CREW_AREA_BENEFICIOS: benefícios de cada nível. Liga quando Rafael decidir os benefícios.
 */
const CREW_AREA_PLACAR = false;
const CREW_AREA_BENEFICIOS = false;

/* Categorias, na ordem do CRM (as frentes). [chave => [rótulo PT, ícone]]. O Speakr saiu da vitrine (vira a aba Relatórios), e com ele a Consultoria. */
function crew_area_categorias(): array {
    return [
        'gaming' => ['Gaming', 'gamepad-2'],
        'agencia' => ['Agência', 'megaphone'],
        'tech' => ['Tech', 'cpu'],
    ];
}

/*
 * Produtos. 'link' = endereço do botão Acessar, para quando o produto estiver contratado (crew_area_contratado).
 * 'breve' => true = ainda não está à venda: aparece com "Em breve", sem botão de contratar.
 * 'oculto' => true = fica fora da vitrine (a plataforma do produto ainda está em desenvolvimento).
 * 'xp' = pontos que o produto soma quando contratado. Os textos (nome, descrição) são os de painel.* em
 * assets/js/lang/en/painel.js; 'tipo' é a etiqueta sobre a foto.
 */
function crew_area_produtos(): array {
    return [
        ['chave' => 'gaming', 'nome' => 'GAMING.CREW', 'categoria' => 'gaming', 'cor' => '240 52 52', 'foto' => 'assets/img/painel-gaming.webp', 'xp' => 1500,
         'tipo' => ['area.prod.tipo.comunidade', 'Comunidade gamer', 'users'],
         'texto' => 'Entre no <strong>Side Quest</strong> e acompanhe as missões, a comunidade gamer e os resultados das ações da sua marca.', 'link' => 'https://side-quest-drab-two.vercel.app/dashboard'],
        ['chave' => 'ruller', 'nome' => 'RULLER.CREW', 'categoria' => 'agencia', 'oculto' => true, // plataforma em desenvolvimento (Rafael, 2026-10-30)
         'cor' => '136 248 72', 'foto' => 'assets/img/painel-ruller.webp', 'xp' => 1000,
         'tipo' => ['area.prod.tipo.campanhas', 'Campanhas', 'target'],
         'texto' => 'Entre no <strong>Ruller</strong> e acompanhe o planejamento, as entregas e os indicadores das suas campanhas.', 'link' => null],
        // Tech: serviços de valor agregado para o provedor oferecer aos assinantes. Capa = logo da marca no fundo dela, 800x600, logo centralizado com a mesma largura.
        // Sem foto ('foto' => null), a capa usa a cor e o ícone.
        ['chave' => 'exitlag', 'nome' => 'EXITLAG', 'categoria' => 'tech', 'cor' => '230 30 40', 'foto' => 'assets/img/produtos/exitlag.png', 'icone' => 'gamepad-2', 'xp' => 500,
         'tipo' => ['area.prod.tipo.games', 'Para gamers', 'zap'],
         'texto' => 'Ofereça o <strong>ExitLag</strong> aos seus assinantes: menos lag e ping mais estável nos jogos online, como serviço de valor agregado no plano.', 'link' => null],
        ['chave' => 'kaspersky', 'nome' => 'KASPERSKY', 'categoria' => 'tech', 'cor' => '0 152 129', 'foto' => 'assets/img/produtos/kaspersky.png', 'icone' => 'shield', 'xp' => 500, 'breve' => true,
         'tipo' => ['area.prod.tipo.seguranca', 'Segurança digital', 'lock'],
         'texto' => 'Ofereça o <strong>Kaspersky</strong> aos seus assinantes: proteção contra vírus, golpes e ameaças online, incluída no plano de internet.', 'link' => null],
        ['chave' => 'socialcomics', 'nome' => 'SOCIAL COMICS', 'categoria' => 'tech', 'cor' => '234 44 70', 'foto' => 'assets/img/produtos/socialcomics.png', 'icone' => 'message-square', 'xp' => 500, 'breve' => true,
         'tipo' => ['area.prod.tipo.leitura', 'Quadrinhos e leitura', 'layers'],
         'texto' => 'Ofereça o <strong>Social Comics</strong> aos seus assinantes: quadrinhos, mangás e livros ilustrados para ler no celular, como benefício do plano.', 'link' => null],
    ];
}

/*
 * Níveis por pontos: [chave, rótulo PT, XP mínimo, benefício PT].
 * Os mínimos e os benefícios são provisórios: os mínimos se calibram com os pontos reais; os benefícios dependem de Rafael.
 */
function crew_area_niveis(): array {
    return [
        ['bronze', 'Bronze', 0, 'Área do cliente com as suas entregas e os seus produtos.'],
        ['prata', 'Prata', 1000, 'Atendimento prioritário com a equipe da Crew.'],
        ['ouro', 'Ouro', 2500, 'Condições especiais na contratação de novos produtos.'],
        ['diamante', 'Diamante', 3500, 'Gerente de conta dedicado e convites para eventos da Crew.'],
    ];
}

/*
 * Se a empresa tem o produto contratado. Vem da Plataforma, por empresa (etapa 3); até lá nenhum produto aparece
 * como contratado, para nenhum cliente ver como seu um produto que não comprou.
 */
function crew_area_contratado(array $produto): bool {
    return false;
}

/* Pontos do cliente: a soma do XP dos produtos com link (conta provisória, só vale com CREW_AREA_PLACAR). */
function crew_area_xp(array $produtos): int {
    $xp = 0;
    foreach ($produtos as $p) if (($p['link'] ?? null) !== null) $xp += (int) $p['xp'];
    return $xp;
}

/*
 * Nível atual, o próximo (null no último) e quanto falta, para a barra de progresso.
 * Devolve ['atual' => nível, 'indice' => int, 'proximo' => nível|null, 'falta' => int, 'pct' => 0..100].
 */
function crew_area_nivel(int $xp): array {
    $niveis = crew_area_niveis();
    $i = 0;
    foreach ($niveis as $n => $nivel) if ($xp >= $nivel[2]) $i = $n;
    $proximo = $niveis[$i + 1] ?? null;
    if ($proximo === null) return ['atual' => $niveis[$i], 'indice' => $i, 'proximo' => null, 'falta' => 0, 'pct' => 100];
    $base = $niveis[$i][2];
    $pct = (int) floor(($xp - $base) * 100 / max(1, $proximo[2] - $base));
    return ['atual' => $niveis[$i], 'indice' => $i, 'proximo' => $proximo, 'falta' => $proximo[2] - $xp, 'pct' => max(0, min(100, $pct))];
}

/* Número no formato brasileiro (1.500). */
function crew_area_num(int $n): string {
    return number_format($n, 0, ',', '.');
}
