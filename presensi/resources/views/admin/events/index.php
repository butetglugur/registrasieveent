<?php
$this->extend('layouts.admin');
$title = 'Event';
$crumb = 'Kelola event & formulir pendaftaran';
$tabs = ['' => 'Semua', 'open' => 'Dibuka', 'draft' => 'Draft', 'closed' => 'Ditutup'];
$statusBadge = ['open' => 'badge-success', 'draft' => 'badge-warning', 'closed' => 'badge-danger'];
?>
<?php $this->section('actions') ?>
  <a class="btn btn-primary" href="<?= e(route('admin.events.create')) ?>"><?= icon('plus') ?><span class="hide-sm">Event baru</span></a>
<?php $this->end() ?>

<div class="flex justify-between items-center wrap gap-1 mb-2">
  <nav class="tabs">
    <?php foreach ($tabs as $k => $label): ?>
      <a href="<?= e(route('admin.events', [], ['status' => $k, 'q' => $q])) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($label) ?>
        <span class="count"><?= $k === '' ? array_sum($counts) : ($counts[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <form method="get" action="<?= e(route('admin.events')) ?>" class="input-icon" style="min-width:240px">
    <?= icon('search') ?><input class="input input-sm" name="q" value="<?= e($q) ?>" placeholder="Cari event…" style="padding-left:2.4rem">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if (!$events): ?>
    <div class="empty">
      <div class="empty-icon"><?= icon('calendar-plus') ?></div>
      <h3><?= $q !== '' || $status !== '' ? 'Tidak ada event yang cocok' : 'Belum ada event' ?></h3>
      <p>Buat event, sesuaikan formulir, lalu bagikan tautan pendaftarannya.</p>
      <a class="btn btn-primary" href="<?= e(route('admin.events.create')) ?>"><?= icon('plus') ?> Buat event</a>
    </div>
  <?php endif; ?>
  <?php foreach ($events as $ev):
    $link = full_url('e/' . $ev['slug']);
    $pct = $ev['total_reg'] ? round($ev['total_checkin'] / $ev['total_reg'] * 100) : 0; ?>
    <div class="event-row" style="<?= e(theme_style($ev['theme'])) ?>">
      <div class="event-thumb"><?php if ($ev['starts_at']): ?><?= date('j', strtotime($ev['starts_at'])) ?><small><?= ID_MONTHS_SHORT[(int) date('n', strtotime($ev['starts_at']))] ?></small><?php else: ?><?= icon('calendar') ?><?php endif; ?></div>
      <div class="info">
        <div class="flex items-center gap-1 wrap">
          <a class="t" href="<?= e(route('admin.events.edit', ['id' => $ev['id']])) ?>"><?= e($ev['title']) ?></a>
          <span class="badge badge-dot <?= $statusBadge[$ev['status']] ?? '' ?>"><?= e(App\Models\Event::STATUSES[$ev['status']] ?? $ev['status']) ?></span>
        </div>
        <div class="s">
          <?php if ($ev['starts_at']): ?><span><?= icon('clock') ?><?= e(date_id($ev['starts_at'])) ?></span><?php endif; ?>
          <?php if ($ev['location']): ?><span><?= icon('map-pin') ?><?= e(str_limit((string) $ev['location'], 40)) ?></span><?php endif; ?>
          <span><?= icon('link') ?>/e/<?= e($ev['slug']) ?></span>
        </div>
      </div>
      <div class="nums">
        <div><b><?= number_id($ev['total_reg']) ?></b><small><?= $ev['quota'] ? 'dari ' . number_id($ev['quota']) : 'Daftar' ?></small></div>
        <div><b><?= number_id($ev['total_checkin']) ?></b><small>Hadir <?= $pct ?>%</small></div>
      </div>
      <div class="acts">
        <button type="button" class="btn btn-sm btn-soft" data-copy="<?= e($link) ?>" title="Salin tautan pendaftaran"><?= icon('copy') ?> Salin link</button>
        <a class="btn btn-sm btn-ghost" href="<?= e(route('admin.registrations', [], ['event' => $ev['id']])) ?>" title="Peserta"><?= icon('users') ?></a>
        <div class="dropdown" data-dropdown>
          <button type="button" class="btn btn-sm btn-ghost" data-dropdown-toggle aria-label="Aksi lain"><?= icon('chevron-down') ?></button>
          <div class="dropdown-menu">
            <a href="<?= e(route('admin.events.edit', ['id' => $ev['id']])) ?>"><?= icon('edit') ?> Edit event</a>
            <a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= icon('external') ?> Buka halaman</a>
            <a href="<?= e(route('admin.checkin', [], ['event' => $ev['id']])) ?>"><?= icon('scan') ?> Check-in</a>
            <a href="<?= e(route('admin.registrations.export', [], ['event' => $ev['id']])) ?>"><?= icon('file') ?> Export Excel</a>
            <a href="<?= e(route('admin.broadcast', [], ['event' => $ev['id']])) ?>"><?= icon('whatsapp') ?> Pesan massal WA</a>
            <hr>
            <?php foreach (App\Models\Event::STATUSES as $sk => $sl): if ($sk === $ev['status']) { continue; } ?>
              <form method="post" action="<?= e(route('admin.events.status', ['id' => $ev['id']])) ?>">
                <?= csrf_field() ?><input type="hidden" name="status" value="<?= e($sk) ?>">
                <button type="submit"><?= icon($sk === 'open' ? 'power' : ($sk === 'closed' ? 'lock' : 'edit')) ?> Jadikan <?= e(strtolower($sl)) ?></button>
              </form>
            <?php endforeach; ?>
            <form method="post" action="<?= e(route('admin.events.duplicate', ['id' => $ev['id']])) ?>">
              <?= csrf_field() ?><button type="submit"><?= icon('layers') ?> Duplikat</button>
            </form>
            <hr>
            <form method="post" action="<?= e(route('admin.events.destroy', ['id' => $ev['id']])) ?>" data-confirm-type="<?= e($ev['slug']) ?>"
                  data-confirm="Hapus event &quot;<?= e($ev['title']) ?>&quot; beserta <?= (int) $ev['total_reg'] ?> data peserta? Tindakan ini tidak dapat dibatalkan.">
              <?= csrf_field() ?><input type="hidden" name="confirm" value="">
              <button type="submit" class="danger"><?= icon('trash') ?> Hapus</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
