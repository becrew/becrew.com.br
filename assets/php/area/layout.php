<?php
/**
 * Crew - barra lateral, barra de cima e rodapé da área logada do cliente (Início, Entregas, Plataformas).
 * Só a área logada usa este arquivo; o resto do site não muda. Cada página faz:
 *   require auth.php; require area/layout.php;
 *   $perfil = crew_area_exigir_login();           // porta de login (e de empresa)
 *   crew_area_abrir('entregas', $perfil, [...]);  // barra lateral + barra de cima
 *   ... conteúdo ...
 *   crew_area_fechar(['area'], ['assets/js/crew-area.js']);
 */
if (!defined('CREW_AUTH')) { http_response_code(404); exit; }
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/produtos.php';

/* Caminho do arquivo com ?v=<hash do conteúdo>, para o navegador pegar a versão nova depois de uma mudança. */
function crew_area_asset(string $rel): string {
    $f = dirname(__DIR__, 3) . '/' . $rel;
    return $rel . '?v=' . (is_file($f) ? substr(md5_file($f), 0, 8) : '0');
}

/**
 * Porta de login das páginas da área. Sem login: volta ao login (303). Usuário sem empresa só vê Plataformas:
 * nas demais páginas ($exigirEmpresa) vai para plataformas.php. Devolve o perfil ['email', 'empresa'].
 */
function crew_area_exigir_login(bool $exigirEmpresa = true): array {
    $perfil = crew_current_profile();
    if (!$perfil) {
        header('Location: area-do-cliente.html', true, 303);
        exit;
    }
    if ($exigirEmpresa && $perfil['empresa'] === null) {
        header('Location: plataformas.php', true, 303);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    return $perfil;
}

/**
 * Abre a página: <head>, barra lateral com o menu da área e a conta, barra de cima com o título. $ativo: inicio, entregas ou plataformas.
 * A área logada tem cara de plataforma: não usa o menu, a barra lateral nem a foto do site (nem o main.js do site).
 */
function crew_area_abrir(string $ativo, array $perfil, array $css = []): void {
    $paginas = [
        'inicio' => 'Início',
        'entregas' => 'Entregas',
        'plataformas' => 'Produtos',
    ];
    $titulo = $paginas[$ativo] ?? 'Área do cliente';
    $chaveTitulo = 'area.' . $ativo;
    $temEmpresa = $perfil['empresa'] !== null;
    $abas = [];
    if ($temEmpresa) {
        $abas[] = ['inicio', 'painel.php', 'Início', 'home'];
        $abas[] = ['entregas', 'entregas.php', 'Entregas', 'calendar'];
        $abas[] = ['relatorios', null, 'Relatórios', 'file-text'];
        $abas[] = ['produtos', null, 'Produtos', 'package'];
    }
    $abas[] = ['plataformas', 'plataformas.php', 'Plataformas', 'layout-grid'];
    $email = $perfil['email'];
    $xp = crew_area_xp(crew_area_produtos());
    $nivel = crew_area_nivel($xp);
?>
<!DOCTYPE html>

<html lang="pt-BR">
<head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-18453522306"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'AW-18453522306');
</script>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title data-i18n="<?= $chaveTitulo ?>.meta"><?= htmlspecialchars($titulo) ?> | Crew</title>
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Space+Grotesk:wght@300..700&display=swap" rel="stylesheet">
<link rel="icon" href="assets/img/crew-favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/favicon.ico" sizes="48x48">
  <link rel="icon" href="/assets/img/crew-icon-192.png" type="image/png" sizes="192x192">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="robots" content="noindex, nofollow"><link href="<?= crew_area_asset('assets/css/main.css') ?>" rel="stylesheet">
<link href="<?= crew_area_asset('assets/css/crew.css') ?>" rel="stylesheet">
<link href="<?= crew_area_asset('assets/css/crew-painel.css') ?>" rel="stylesheet">
<link href="<?= crew_area_asset('assets/css/crew-app.css') ?>" rel="stylesheet">
<?php foreach ($css as $c) { ?>
<link href="<?= crew_area_asset($c) ?>" rel="stylesheet">
<?php } ?>
</head>
<body class="crew-app" id="page-top">
<div class="app">
<!-- Barra lateral: no celular vira gaveta, aberta pelo botão da barra de cima -->
<aside class="app-side" id="app-side">
<div class="app-side__brand">
<a href="<?= $temEmpresa ? 'painel.php' : 'plataformas.php' ?>" class="app-side__logo"><img alt="Crew" src="assets/img/crew-logo-dark.svg"></a>
<span class="app-side__tag" data-i18n="painel.kicker">Área do cliente</span>
<button type="button" class="app-icon-btn app-side__close" data-app-close aria-label="Fechar menu" data-i18n-attr="aria-label:area.menu.fechar"><i data-lucide="x"></i></button>
</div>
<nav class="app-nav" aria-label="Área do cliente" data-i18n-attr="aria-label:area.nav.label">
<?php foreach ($abas as [$chave, $href, $rotulo, $icone]) { ?>
<?php if ($href === null) { ?>
<span class="app-nav__link is-soon" aria-disabled="true"><i data-lucide="<?= $icone ?>"></i><span data-i18n="area.nav.<?= $chave ?>"><?= $rotulo ?></span><em data-i18n="area.nav.soon">em breve</em></span>
<?php } else { ?>
<a class="app-nav__link" href="<?= $href ?>"<?= $chave === $ativo ? ' aria-current="page"' : '' ?>><i data-lucide="<?= $icone ?>"></i><span data-i18n="area.nav.<?= $chave ?>"><?= $rotulo ?></span><?= $chave === 'entregas' ? '<b class="app-nav__badge" data-nav-esperando hidden></b>' : '' ?></a>
<?php } ?>
<?php } ?>
</nav>
<div class="app-side__foot">
<a class="app-side__site" href="index.html"><i data-lucide="globe"></i><span data-i18n="area.site">Ir para o site</span><i data-lucide="arrow-up-right"></i></a>
<a class="app-nivel" href="plataformas.php#pontos" data-nivel="<?= $nivel['atual'][0] ?>">
<span class="app-nivel__topo"><img class="app-nivel__selo" src="assets/img/niveis/<?= $nivel['atual'][0] ?>.png" alt="" width="32" height="32"><span class="app-nivel__nome"><span data-i18n="area.xp.nivel">Nível</span> <span data-i18n="area.nivel.<?= $nivel['atual'][0] ?>"><?= $nivel['atual'][1] ?></span></span><span class="app-nivel__xp"><?= crew_area_num($xp) ?> XP</span></span>
<span class="app-nivel__barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $nivel['pct'] ?>"><span style="width: <?= $nivel['pct'] ?>%"></span></span>
<?php if ($nivel['proximo']) { ?>
<span class="app-nivel__falta"><?= crew_area_num($nivel['falta']) ?> XP <span data-i18n="area.xp.para">para</span> <span data-i18n="area.nivel.<?= $nivel['proximo'][0] ?>"><?= $nivel['proximo'][1] ?></span></span>
<?php } else { ?>
<span class="app-nivel__falta" data-i18n="area.xp.maximo">Você está no nível máximo.</span>
<?php } ?>
</a>
<div class="app-user">
<span class="app-user__avatar" aria-hidden="true"><i data-lucide="user"></i></span>
<span class="app-user__info">
<span class="app-user__email" title="<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></span>
<?php if ($temEmpresa) { ?><span class="app-user__empresa"><?= htmlspecialchars($perfil['empresa']) ?></span><?php } ?>
</span>
<a class="app-icon-btn" href="assets/php/logout.php" aria-label="Sair" title="Sair" data-i18n-attr="aria-label:painel.logout"><i data-lucide="log-out"></i></a>
</div>
</div>
</aside>
<div class="app-scrim" data-app-close hidden></div>
<div class="app-main">
<header class="app-top">
<button type="button" class="app-icon-btn app-top__menu" data-app-open aria-controls="app-side" aria-expanded="false" aria-label="Abrir menu" data-i18n-attr="aria-label:nav.toggle"><i data-lucide="menu"></i></button>
<div class="app-top__title">
<span class="app-top__crumb" data-i18n="painel.kicker">Área do cliente</span>
<h1 class="app-top__h1" data-i18n="<?= $chaveTitulo ?>.title"><?= htmlspecialchars($titulo) ?></h1>
</div>
<a class="app-xp" href="plataformas.php#pontos" data-nivel="<?= $nivel['atual'][0] ?>" title="Seus pontos de experiência" data-i18n-attr="title:area.xp.titulo"><img class="app-xp__selo" src="assets/img/niveis/<?= $nivel['atual'][0] ?>.png" alt="" width="20" height="20"><strong><?= crew_area_num($xp) ?></strong><span>XP</span><em data-i18n="area.nivel.<?= $nivel['atual'][0] ?>"><?= $nivel['atual'][1] ?></em></a>
<div class="crew-lang app-top__lang" role="group" aria-label="Idioma" data-i18n-attr="aria-label:lang.label">
<button type="button" class="crew-lang__btn" data-lang-switch="pt">PT</button>
<span class="crew-lang__sep"></span>
<button type="button" class="crew-lang__btn" data-lang-switch="en">EN</button>
</div>
</header>
<main class="app-content crew-area crew-area--<?= htmlspecialchars($ativo) ?>">
<?php
}

/* Fecha a página e carrega os scripts: textos em inglês ($langs, de assets/js/lang/en/<nome>.js), a gaveta do menu (crew-app.js) e os scripts da página. */
function crew_area_fechar(array $langs = [], array $js = []): void {
?>
</main>
</div>
</div>
<script src="<?= crew_area_asset('assets/js/lucide-local.js') ?>"></script>
<script src="<?= crew_area_asset('assets/js/lang/en/common.js') ?>"></script>
<script src="<?= crew_area_asset('assets/js/lang/en/area.js') ?>"></script>
<?php foreach ($langs as $l) { ?>
<script src="<?= crew_area_asset('assets/js/lang/en/' . $l . '.js') ?>"></script>
<?php } ?>
<script src="<?= crew_area_asset('assets/js/i18n.js') ?>"></script>
<script src="<?= crew_area_asset('assets/js/crew-app.js') ?>"></script>
<?php foreach ($js as $s) { ?>
<script src="<?= crew_area_asset($s) ?>"></script>
<?php } ?>
</body>
</html>
<?php
}
