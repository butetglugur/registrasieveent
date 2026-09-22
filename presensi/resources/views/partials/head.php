<?php /** Elemen <head> bersama. Variabel opsional: $title, $description, $themeKey */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? '') !== '' ? $title . ' · ' . app_name() : app_name()) ?></title>
<meta name="description" content="<?= e($description ?? setting('tagline', '')) ?>">
<meta name="theme-color" content="#7c3aed">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta property="og:title" content="<?= e($title ?? app_name()) ?>">
<meta property="og:description" content="<?= e($description ?? setting('tagline', '')) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script nonce="<?= e(csp_nonce()) ?>">(function(){try{var t=localStorage.getItem('theme');if(!t){t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}document.documentElement.setAttribute('data-theme',t)}catch(e){}})();</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
