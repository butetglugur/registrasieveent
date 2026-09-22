<?php
$__user = auth_user();
$__path = request()->path();
$__nav = [
    ['Menu', null, null, null],
    ['Dasbor', 'admin.dashboard', 'dashboard', '/admin'],
    ['Event', 'admin.events', 'calendar', '/admin/event', true],
    ['Peserta', 'admin.registrations', 'users', '/admin/peserta'],
    ['Check-in', 'admin.checkin', 'scan', '/admin/checkin'],
    ['Sistem', null, null, null, true],
    ['Pengguna', 'admin.users', 'shield', '/admin/pengguna', true],
    ['Pengaturan', 'admin.settings', 'settings', '/admin/pengaturan', true],
    ['Log Aktivitas', 'admin.activity', 'activity', '/admin/aktivitas', true],
];
$__initials = static function (string $n): string {
    $parts = preg_split('/\s+/', trim($n)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) { $s .= mb_strtoupper(mb_substr($p, 0, 1)); }
    return $s ?: '?';
};
?>
<!doctype html>
<html lang="id">
<head>
<?= $this->partial('partials.head', ['title' => $title ?? 'Admin']) ?>
<meta name="robots" content="noindex, nofollow">
</head>
<body class="admin">
<?= $this->partial('partials.flash') ?>
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(route('admin.dashboard')) ?>">
      <span class="brand-mark"><?= icon('sparkles') ?></span>
      <span><?= e(app_name()) ?><small>Panel Admin</small></span>
    </a>
    <nav class="nav">
      <?php foreach ($__nav as $__item):
        $__adminOnly = $__item[4] ?? false;
        if ($__adminOnly && !is_admin()) { continue; }
        if ($__item[1] === null): ?>
          <div class="nav-title"><?= e($__item[0]) ?></div>
        <?php else:
          $__active = $__item[3] === '/admin' ? $__path === '/admin' : str_starts_with($__path, $__item[3]); ?>
          <a href="<?= e(route($__item[1])) ?>" class="<?= $__active ? 'active' : '' ?>" <?= $__active ? 'aria-current="page"' : '' ?>><?= icon($__item[2]) ?><?= e($__item[0]) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <div class="nav-title">Lainnya</div>
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('globe') ?>Lihat situs publik</a>
    </nav>
    <div class="sidebar-foot">
      <div class="user-card">
        <span class="avatar"><?= e($__initials((string) $__user['name'])) ?></span>
        <a href="<?= e(route('admin.account')) ?>" style="text-decoration:none;min-width:0">
          <div class="name"><?= e(str_limit((string) $__user['name'], 18)) ?></div>
          <div class="role"><?= e(App\Models\User::ROLES[$__user['role']] ?? $__user['role']) ?></div>
        </a>
        <form method="post" action="<?= e(route('logout')) ?>">
          <?= csrf_field() ?>
          <button type="submit" title="Logout" aria-label="Logout"><?= icon('logout') ?></button>
        </form>
      </div>
    </div>
  </aside>
  <div class="backdrop" data-sidebar-close></div>
  <div class="main">
    <header class="topbar">
      <button type="button" class="btn btn-ghost btn-icon menu-btn" data-sidebar-toggle aria-label="Menu"><?= icon('menu') ?></button>
      <div>
        <?php if (!empty($crumb)): ?><div class="crumbs"><?= e($crumb) ?></div><?php endif; ?>
        <h1><?= e($title ?? 'Admin') ?></h1>
      </div>
      <div class="actions">
        <?= $this->yield('actions') ?>
        <button type="button" class="btn btn-ghost btn-icon" data-theme-toggle aria-label="Ganti tema"><?= icon('moon') ?></button>
      </div>
    </header>
    <main class="content">
      <?= $this->yield('content') ?>
    </main>
    <footer class="admin-footer"><?= e(app_name()) ?> v<?= e(config('app.version')) ?> · Waktu server <?= e(date_id(now())) ?> <?= e(date('T')) ?></footer>
  </div>
</div>
<div id="modal-root"></div>
</body>
</html>
