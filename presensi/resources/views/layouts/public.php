<!doctype html>
<html lang="id">
<head>
<?= $this->partial('partials.head') ?>
</head>
<body class="public" style="<?= e(theme_style($themeKey ?? setting('default_theme', 'violet'))) ?>">
<div class="bg-blobs" aria-hidden="true"><span></span><span></span><span></span></div>
<header class="topbar-public">
  <div class="container">
    <div class="inner glass">
      <a class="brand" href="<?= e(url('/')) ?>">
        <span class="brand-mark"><?= icon('sparkles') ?></span>
        <span><?= e(app_name()) ?></span>
      </a>
      <div class="flex items-center gap-1">
        <button type="button" class="btn btn-ghost btn-icon" data-theme-toggle aria-label="Ganti tema"><?= icon('moon') ?></button>
      </div>
    </div>
  </div>
</header>
<?= $this->partial('partials.flash') ?>
<main class="public-main">
<?= $this->yield('content') ?>
</main>
<footer class="public-footer">
  <?php $__footer = (string) setting('footer_text', ''); ?>
  <?= $__footer !== '' ? e($__footer) : '© ' . date('Y') . ' ' . e(setting('org_name', '') ?: app_name()) ?>
</footer>
<?= $this->yield('scripts') ?>
</body>
</html>
