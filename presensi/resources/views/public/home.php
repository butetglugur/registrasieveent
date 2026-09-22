<?php $this->extend('layouts.public'); ?>
<?php $title = ''; ?>
<div class="container">
  <section class="hero">
    <span class="eyebrow"><?= icon('sparkles') ?> Registrasi & Presensi Digital</span>
    <h1>Selamat datang di <span class="grad-text"><?= e(app_name()) ?></span></h1>
    <p><?= e(setting('tagline', '')) ?></p>
  </section>

  <?php if (!$events): ?>
    <div class="card empty">
      <div class="empty-icon"><?= icon('calendar') ?></div>
      <h3>Belum ada event yang dibuka</h3>
      <p>Silakan kembali lagi nanti atau hubungi panitia untuk informasi lebih lanjut.</p>
    </div>
  <?php else: ?>
    <div class="event-grid">
      <?php foreach ($events as $ev): ?>
        <?php [$isOpen] = App\Models\Event::registrationState($ev, (int) $ev['total_reg']); ?>
        <a class="card event-card" href="<?= e(route('event.show', ['slug' => $ev['slug']])) ?>" style="<?= e(theme_style($ev['theme'])) ?>">
          <div class="cover">
            <span class="badge"><?= $isOpen ? 'Pendaftaran dibuka' : 'Ditutup' ?></span>
            <h3><?= e($ev['title']) ?></h3>
          </div>
          <div class="meta">
            <?php if ($ev['starts_at']): ?><div><?= icon('calendar') ?><?= e(day_id($ev['starts_at']) . ', ' . date_id($ev['starts_at'])) ?></div><?php endif; ?>
            <?php if ($ev['location']): ?><div><?= icon('map-pin') ?><?= e($ev['location']) ?></div><?php endif; ?>
            <?php if (setting('show_count_public', '1') === '1'): ?><div><?= icon('users') ?><?= number_id($ev['total_reg']) ?> peserta terdaftar</div><?php endif; ?>
          </div>
          <div class="foot"><span><?= $isOpen ? 'Daftar sekarang' : 'Lihat detail' ?></span><?= icon('arrow-right') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
