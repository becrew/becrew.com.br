<?php
/**
 * Crew - topo, menu e rodapé da área logada do cliente (Início, Entregas, Plataformas).
 * Só a área logada usa este arquivo; o resto do site não muda. Cada página faz:
 *   require auth.php; require area/layout.php;
 *   $perfil = crew_area_exigir_login();           // porta de login (e de empresa)
 *   crew_area_abrir('entregas', $perfil, [...]);  // topo + menu
 *   ... conteúdo ...
 *   crew_area_fechar(['area'], ['assets/js/crew-area.js']);
 */
if (!defined('CREW_AUTH')) { http_response_code(404); exit; }
require_once __DIR__ . '/../auth.php';

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

/* Abre a página: <head>, topo, barra lateral, painel do menu do site, título e o menu da área. $ativo: inicio, entregas ou plataformas. */
function crew_area_abrir(string $ativo, array $perfil, array $css = []): void {
    $paginas = [
        'inicio' => 'Início',
        'entregas' => 'Entregas',
        'plataformas' => 'Suas plataformas',
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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Space+Grotesk:wght@300..700&family=Playfair+Display:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
<link rel="icon" href="assets/img/crew-favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/favicon.ico" sizes="48x48">
  <link rel="icon" href="/assets/img/crew-icon-192.png" type="image/png" sizes="192x192">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
<!-- Icons (Lucide) -->
<meta name="robots" content="noindex, nofollow"><link href="<?= crew_area_asset('assets/css/main.css') ?>" rel="stylesheet">
<link href="<?= crew_area_asset('assets/css/crew.css') ?>" rel="stylesheet">
<link href="<?= crew_area_asset('assets/css/crew-painel.css') ?>" rel="stylesheet">
<?php foreach ($css as $c) { ?>
<link href="<?= crew_area_asset($c) ?>" rel="stylesheet">
<?php } ?>
</head>
<body class="relative h-[100dvh] w-full bg-brand-bg text-white tft-page-painel" id="page-top">
<!-- ============================================== -->
<!-- MOBILE HEADER (< 768px)                        -->
<!-- ============================================== -->
<!-- MOBILE HEADER -->
<header class="fixed top-0 left-0 w-full h-[70px] bg-white z-[60] flex md:hidden items-center justify-between border-b border-gray-200 text-brand-sidebar">
<a class="flex items-center" href="index.html">
<img alt="Crew" class="h-8 w-auto" src="assets/img/crew-logo-dark.svg">
</a>
<div class="crew-lang crew-lang--mobile" role="group" aria-label="Idioma" data-i18n-attr="aria-label:lang.label">
<button type="button" class="crew-lang__btn" data-lang-switch="pt">PT</button>
<span class="crew-lang__sep"></span>
<button type="button" class="crew-lang__btn" data-lang-switch="en">EN</button>
</div>
<a class="crew-client-icon" data-crew-client href="area-do-cliente.html" aria-label="Área do cliente" data-i18n-attr="aria-label:nav.client" aria-current="page"><i data-lucide="user"></i></a>
<button aria-label="Abrir menu" data-i18n-attr="aria-label:nav.toggle" class="menu-toggle w-[50px] h-[50px] mr-3 rounded-sm bg-brand-sidebar flex flex-col items-center justify-center cursor-pointer relative shadow-md z-[60]" type="button">
<span class="relative w-[28px] h-[18px] block">
<span class="line-1 hamburger-line absolute top-0 right-0 w-[28px] h-[2px] bg-brand-lime rounded-full"></span>
<span class="line-2 hamburger-line absolute top-[8px] right-0 w-[28px] h-[2px] bg-brand-lime rounded-full"></span>
<span class="line-3 hamburger-line absolute top-[16px] right-0 w-[20px] h-[2px] bg-brand-lime rounded-full"></span>
</span>
</button>
</header>
<!-- FIXED LEFT SIDEBAR -->
<aside class="hidden md:flex w-[140px] h-screen fixed left-0 top-0 z-50 bg-white border-r border-gray-200 shadow-[10px_0_30px_rgba(10,10,10,0.01)] text-brand-sidebar">
<div class="w-[40px] h-full bg-brand-lime border-r border-gray-200 flex items-center justify-center overflow-hidden shrink-0">
<span class="text-vertical text-[9px] tracking-[0.2em] font-medium whitespace-nowrap uppercase" data-i18n="sidebar.strip">Estratégia - Conteúdo - Mídia - Branding - Dados</span>
</div>
<div class="flex-1 h-full flex flex-col items-center justify-between pb-8">
<div class="w-full h-[140px] flex items-center justify-center relative after:absolute after:bottom-0 after:left-4 after:right-4 after:h-px after:bg-gray-100 shrink-0">
<button aria-label="Abrir menu" data-i18n-attr="aria-label:nav.toggle" class="menu-toggle w-[70px] h-[70px] rounded-sm bg-brand-sidebar flex flex-col items-center justify-center cursor-pointer relative group shadow-md z-50" type="button">
<span class="relative w-[28px] h-[18px] block">
<span class="line-1 hamburger-line absolute top-0 right-0 w-[28px] h-[2px] bg-brand-lime rounded-full"></span>
<span class="line-2 hamburger-line absolute top-[8px] right-0 w-[28px] h-[2px] bg-brand-lime rounded-full"></span>
<span class="line-3 hamburger-line absolute top-[16px] right-0 w-[20px] group-hover:w-[28px] h-[2px] bg-brand-lime rounded-full"></span>
</span>
</button>
</div>
<div class="crew-lang crew-lang--sidebar" role="group" aria-label="Idioma" data-i18n-attr="aria-label:lang.label">
<button type="button" class="crew-lang__btn" data-lang-switch="pt">PT</button>
<span class="crew-lang__sep"></span>
<button type="button" class="crew-lang__btn" data-lang-switch="en">EN</button>
</div>
<a class="crew-client-btn" data-crew-client href="area-do-cliente.html" aria-label="Área do cliente" data-i18n-attr="aria-label:nav.client" aria-current="page"><i data-lucide="user"></i><span data-i18n="nav.client.short">Cliente</span></a>
<div class="flex flex-col items-center gap-10 mt-8">
<a class="tft-sidebar-logo-vertical" href="index.html">
<img alt="Crew" src="assets/img/crew-logo-dark.svg">
</a>
</div>
<div class="flex-1"></div>
<div class="flex flex-col items-center">
<div class="relative group flex flex-col items-center mb-6 w-[100px]">
<div class="absolute bottom-[52px] pb-8 flex flex-col gap-1 items-center pointer-events-none group-hover:pointer-events-auto z-10 w-full">
<div class="social-item social-1 font-bold text-xs opacity-50"><a href="https://www.instagram.com/agencia_crew/" rel="noopener noreferrer" target="_blank">In.</a></div>
<div class="social-item social-2 font-bold text-xs opacity-50"><a href="https://x.com/agencia_crew" rel="noopener noreferrer" target="_blank">X.</a></div>
<div class="social-item social-3 font-bold text-xs opacity-50"><a href="https://www.tiktok.com/@agencia_crew" rel="noopener noreferrer" target="_blank">Tk.</a></div>
<div class="social-item social-4 font-bold text-xs opacity-50"><a href="https://www.youtube.com/@agencia_crew" rel="noopener noreferrer" target="_blank">Yt.</a></div>
</div>
<div class="w-[58px] h-[58px] rounded-full border border-brand-sidebar flex items-center justify-center bg-brand-sidebar text-brand-lime shadow-sm transition-all hover:bg-brand-lime hover:text-brand-sidebar mt-auto">
<i class="w-6 h-6 animate-wave" data-lucide="arrow-up-right"></i>
</div>
</div>
<span class="text-vertical text-[10px] tracking-[0.15em] opacity-80 uppercase">© 2026 Crew.</span>
</div>
</div>
</aside>
<!-- MENU PANEL -->
<nav class="fixed left-0 md:left-[140px] top-[70px] md:top-0 h-[calc(100dvh-70px)] md:h-screen w-full md:w-[360px] bg-brand-menu border-r border-white/5 transform -translate-x-full transition-transform duration-600 ease-[cubic-bezier(0.77,0,0.175,1)] z-[55] md:z-40 overflow-y-auto menu-no-scrollbar text-white tft-menu-glass" id="menu-panel">
<div class="w-full h-full pt-12 md:pt-[120px] pb-20 flex flex-col px-10">
<ul class="w-full mt-4 flex flex-col">
<li class="w-full border-b border-white/10 relative">
<div class="py-6 w-full flex items-center justify-between group crew-menu-row">
<span class="font-body text-[11px] text-white/30 tracking-widest">-01</span>
<a class="crew-menu-link flex items-center gap-3 ml-auto relative z-20" href="index.html">
<span class="font-display text-2xl text-white/70 group-hover:text-white transition-colors" data-i18n="nav.home">Início</span>
<span class="w-5 h-5 rounded-full bg-brand-lime flex items-center justify-center">
<i class="w-3 h-3 text-brand-sidebar" data-lucide="arrow-up-right"></i>
</span>
</a>
</div>
</li>
<li class="w-full border-b border-white/10 relative">
<div class="py-6 w-full flex items-center justify-between group crew-menu-row">
<span class="font-body text-[11px] text-white/30 tracking-widest">-02</span>
<a class="crew-menu-link flex items-center gap-3 ml-auto relative z-20" href="sobre.html">
<span class="font-display text-2xl text-white/70 group-hover:text-white transition-colors" data-i18n="nav.about">A Crew</span>
<span class="w-5 h-5 rounded-full bg-brand-lime flex items-center justify-center">
<i class="w-3 h-3 text-brand-sidebar" data-lucide="arrow-up-right"></i>
</span>
</a>
</div>
</li>
<li class="w-full border-b border-white/10 relative">
<div class="py-6 w-full flex items-center justify-between group crew-menu-row">
<span class="font-body text-[11px] text-white/30 tracking-widest">-03</span>
<a class="crew-menu-link flex items-center gap-3 ml-auto relative z-20" href="servicos.html">
<span class="font-display text-2xl text-white/70 group-hover:text-white transition-colors" data-i18n="nav.services">Serviços</span>
<span class="w-5 h-5 rounded-full bg-brand-lime flex items-center justify-center">
<i class="w-3 h-3 text-brand-sidebar" data-lucide="arrow-up-right"></i>
</span>
</a>
</div>
</li>
<li class="w-full border-b border-white/10 relative">
<div class="py-6 w-full flex items-center justify-between group crew-menu-row">
<span class="font-body text-[11px] text-white/30 tracking-widest">-04</span>
<a class="crew-menu-link flex items-center gap-3 ml-auto relative z-20" href="comunidades.html">
<span class="font-display text-2xl text-white/70 group-hover:text-white transition-colors" data-i18n="nav.communities">Comunidades</span>
<span class="w-5 h-5 rounded-full bg-brand-lime flex items-center justify-center">
<i class="w-3 h-3 text-brand-sidebar" data-lucide="arrow-up-right"></i>
</span>
</a>
</div>
</li>
<li class="w-full border-b border-white/10 relative">
<div class="py-6 w-full flex items-center justify-between group crew-menu-row">
<span class="font-body text-[11px] text-white/30 tracking-widest">-05</span>
<a class="crew-menu-link flex items-center gap-3 ml-auto relative z-20" href="blog.html">
<span class="font-display text-2xl text-white/70 group-hover:text-white transition-colors" data-i18n="nav.blog">Conteúdo</span>
<span class="w-5 h-5 rounded-full bg-brand-lime flex items-center justify-center">
<i class="w-3 h-3 text-brand-sidebar" data-lucide="arrow-up-right"></i>
</span>
</a>
</div>
</li>
<li class="w-full border-b border-white/10 relative">
<div class="py-6 w-full flex items-center justify-between group crew-menu-row">
<span class="font-body text-[11px] text-white/30 tracking-widest">-06</span>
<a class="crew-menu-link flex items-center gap-3 ml-auto relative z-20" href="contato.html">
<span class="font-display text-2xl text-white/70 group-hover:text-white transition-colors" data-i18n="nav.contact">Contato</span>
<span class="w-5 h-5 rounded-full bg-brand-lime flex items-center justify-center">
<i class="w-3 h-3 text-brand-sidebar" data-lucide="arrow-up-right"></i>
</span>
</a>
</div>
</li>
</ul>
<div class="crew-menu-foot">
<a class="crew-menu-client" data-crew-client href="area-do-cliente.html" aria-current="page"><i data-lucide="user"></i><span data-i18n="nav.client">Área do cliente</span><i data-lucide="arrow-up-right"></i></a>
<a class="crew-menu-mail" href="mailto:contato@becrew.com.br">contato@becrew.com.br</a>
<div class="crew-lang crew-lang--menu" role="group" aria-label="Idioma" data-i18n-attr="aria-label:lang.label">
<button type="button" class="crew-lang__btn" data-lang-switch="pt">PT</button>
<span class="crew-lang__sep"></span>
<button type="button" class="crew-lang__btn" data-lang-switch="en">EN</button>
</div>
</div>
</div>
</nav>
<!-- ============================================== -->
<!-- MAIN CONTENT AREA                              -->
<!-- ============================================== -->
<div class="absolute left-0 md:left-[140px] right-0 top-[70px] md:top-0 bottom-0 overflow-y-auto z-10 bg-brand-bg" id="main-scroll-container">

<main class="crew-painel crew-area crew-area--<?= htmlspecialchars($ativo) ?>">
<img alt="" class="crew-painel__photo" src="assets/img/sobre-fachada.webp" aria-hidden="true">
<div class="crew-painel__shade" aria-hidden="true"></div>
<header class="crew-painel__head">
<div>
<span class="crew-painel__kicker" data-i18n="painel.kicker">Área do cliente</span>
<h1 class="crew-painel__title" data-i18n="<?= $chaveTitulo ?>.title"><?= htmlspecialchars($titulo) ?></h1>
</div>
</header>
<nav class="crew-areanav" aria-label="Área do cliente" data-i18n-attr="aria-label:area.nav.label">
<?php foreach ($abas as [$chave, $href, $rotulo, $icone]) { ?>
<?php if ($href === null) { ?>
<span class="crew-areanav__link is-soon" aria-disabled="true"><i data-lucide="<?= $icone ?>"></i><span data-i18n="area.nav.<?= $chave ?>"><?= $rotulo ?></span><em data-i18n="area.nav.soon">em breve</em></span>
<?php } else { ?>
<a class="crew-areanav__link" href="<?= $href ?>"<?= $chave === $ativo ? ' aria-current="page"' : '' ?>><i data-lucide="<?= $icone ?>"></i><span data-i18n="area.nav.<?= $chave ?>"><?= $rotulo ?></span></a>
<?php } ?>
<?php } ?>
<a class="crew-areanav__link crew-areanav__link--sair" href="assets/php/logout.php"><i data-lucide="log-out"></i><span data-i18n="painel.logout">Sair</span></a>
</nav>
<?php
}

/* Fecha a página e carrega os scripts: textos em inglês ($langs, de assets/js/lang/en/<nome>.js) e os scripts da página. */
function crew_area_fechar(array $langs = [], array $js = []): void {
?>
</main>
</div>
<script src="<?= crew_area_asset('assets/js/lucide-local.js') ?>"></script>
<script src="<?= crew_area_asset('assets/js/lang/en/common.js') ?>"></script>
<script src="<?= crew_area_asset('assets/js/lang/en/area.js') ?>"></script>
<?php foreach ($langs as $l) { ?>
<script src="<?= crew_area_asset('assets/js/lang/en/' . $l . '.js') ?>"></script>
<?php } ?>
<script src="<?= crew_area_asset('assets/js/i18n.js') ?>"></script>
<?php foreach ($js as $s) { ?>
<script src="<?= crew_area_asset($s) ?>"></script>
<?php } ?>
<script src="<?= crew_area_asset('assets/js/main.js') ?>"></script>
</body>
</html>
<?php
}
