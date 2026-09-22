<?php
$this->extend('layouts.admin');
$title = 'Peserta';
$crumb = $event ? $event['title'] : 'Semua event';
$exportQuery = array_filter(['event' => $filters['event_id'] ?: null, 'q' => $filters['q'], 'status' => $filters['status'], 'from' => $filters['from'], 'to' => $filters['to']]);
$rate = $stats['total'] ? round($stats['checked_in'] / $stats['total'] * 100) : 0;
?>
<?php $this->section('actions') ?>
  <?php if (is_admin()): ?>
  <div class="dropdown" data-dropdown>
    <button type="button" class="btn btn-soft" data-dropdown-toggle><?= icon('download') ?><span class="hide-sm">Export</span><?= icon('chevron-down') ?></button>
    <div class="dropdown-menu">
      <a href="<?= e(route('admin.registrations.export', [], $exportQuery)) ?>"><?= icon('file') ?> Excel (.xlsx)</a>
      <a href="<?= e(route('admin.registrations.export', [], $exportQuery + ['format' => 'csv'])) ?>"><?= icon('download') ?> CSV</a>
    </div>
  </div>
  <?php endif; ?>
  <a class="btn btn-primary" href="<?= e(route('admin.checkin', [], ['event' => $filters['event_id'] ?: null])) ?>"><?= icon('scan') ?><span class="hide-sm">Check-in</span></a>
<?php $this->end() ?>

<div class="stats">
  <div class="card stat"><div class="stat-icon c-violet"><?= icon('users') ?></div><div><div class="value"><?= number_id($stats['total']) ?></div><div class="label-s">Total pendaftar</div></div></div>
  <div class="card stat"><div class="stat-icon c-emerald"><?= icon('user-check') ?></div><div><div class="value"><?= number_id($stats['checked_in']) ?></div><div class="label-s">Hadir (<?= $rate ?>%)</div></div></div>
  <div class="card stat"><div class="stat-icon c-amber"><?= icon('clock') ?></div><div><div class="value"><?= number_id($stats['total'] - $stats['checked_in']) ?></div><div class="label-s">Belum hadir</div></div></div>
  <div class="card stat"><div class="stat-icon c-pink"><?= icon('user-plus') ?></div><div><div class="value"><?= number_id($stats['today']) ?></div><div class="label-s">Daftar hari ini</div></div></div>
</div>

<div class="card">
  <form method="get" action="<?= e(route('admin.registrations')) ?>" class="toolbar" data-autosubmit>
    <div class="input-icon"><?= icon('search') ?><input class="input input-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari nama, WA, kode, instansi…" style="padding-left:2.4rem" data-debounce></div>
    <select class="select select-sm" name="event" aria-label="Event">
      <option value="">Semua event</option>
      <?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>" <?= (int) $filters['event_id'] === (int) $ev['id'] ? 'selected' : '' ?>><?= e(str_limit((string) $ev['title'], 40)) ?></option><?php endforeach; ?>
    </select>
    <select class="select select-sm" name="status" aria-label="Kehadiran">
      <option value="">Semua status</option>
      <option value="in" <?= $filters['status'] === 'in' ? 'selected' : '' ?>>Sudah hadir</option>
      <option value="out" <?= $filters['status'] === 'out' ? 'selected' : '' ?>>Belum hadir</option>
    </select>
    <input type="date" class="input input-sm" name="from" value="<?= e($filters['from']) ?>" style="width:auto" aria-label="Dari tanggal">
    <input type="date" class="input input-sm" name="to" value="<?= e($filters['to']) ?>" style="width:auto" aria-label="Sampai tanggal">
    <select class="select select-sm" name="sort" aria-label="Urutkan">
      <?php foreach (['newest' => 'Terbaru', 'oldest' => 'Terlama', 'name' => 'Nama A-Z', 'checkin' => 'Check-in terbaru'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <select class="select select-sm" name="per" aria-label="Per halaman">
      <?php foreach ([10, 25, 50, 100] as $n): ?><option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?>/hal</option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-sm btn-soft">Terapkan</button></noscript>
    <?php if (array_filter([$filters['q'], $filters['event_id'], $filters['status'], $filters['from'], $filters['to']])): ?>
      <a class="btn btn-sm btn-ghost" href="<?= e(route('admin.registrations')) ?>"><?= icon('x') ?> Reset</a>
    <?php endif; ?>
  </form>

  <?php if (is_admin()): ?>
  <form method="post" action="<?= e(route('admin.registrations.bulk')) ?>" id="bulk-form">
    <?= csrf_field() ?>
    <div class="bulkbar" data-bulkbar>
      <span><b data-bulk-count>0</b> dipilih</span>
      <button class="btn btn-sm btn-success" name="action" value="checkin" type="submit"><?= icon('user-check') ?> Tandai hadir</button>
      <button class="btn btn-sm btn-danger" name="action" value="delete" type="submit" data-confirm="Hapus semua peserta yang dipilih? Tindakan ini tidak dapat dibatalkan."><?= icon('trash') ?> Hapus</button>
    </div>
  </form>
  <?php endif; ?>

  <?php if (!$list->items): ?>
    <div class="empty">
      <div class="empty-icon"><?= icon('users') ?></div>
      <h3>Belum ada peserta</h3>
      <p><?= $filters['q'] !== '' ? 'Tidak ada hasil untuk pencarian ini.' : 'Bagikan tautan pendaftaran event untuk mulai menerima peserta.' ?></p>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table table-cards">
      <thead><tr>
        <?php if (is_admin()): ?><th class="check"><input type="checkbox" data-check-all aria-label="Pilih semua"></th><?php endif; ?>
        <th>Peserta</th><th>WhatsApp</th><th>Event</th><th>Terdaftar</th><th>Kehadiran</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($list->items as $r): ?>
        <tr style="<?= e(theme_style($r['theme'])) ?>">
          <?php if (is_admin()): ?><td class="check"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" form="bulk-form" data-check-item aria-label="Pilih <?= e($r['name']) ?>"></td><?php endif; ?>
          <td class="td-main">
            <a class="person" href="<?= e(route('admin.registrations.show', ['id' => $r['id']])) ?>" style="text-decoration:none">
              <span class="avatar"><?= e(mb_strtoupper(mb_substr((string) $r['name'], 0, 1))) ?></span>
              <span style="min-width:0"><span class="person-name"><?= e($r['name']) ?></span><span class="person-sub mono"><?= e($r['code']) ?><?= $r['representative'] ? ' · ' . e(str_limit((string) $r['representative'], 30)) : '' ?></span></span>
            </a>
          </td>
          <td data-label="WhatsApp"><a href="<?= e(wa_link((string) $r['wa'])) ?>" target="_blank" rel="noopener" class="nowrap"><?= e($r['wa']) ?></a></td>
          <td data-label="Event"><span class="small"><?= e(str_limit((string) $r['event_title'], 32)) ?></span></td>
          <td data-label="Terdaftar"><span class="small nowrap" title="<?= e(date_id($r['created_at'])) ?>"><?= e(date_id($r['created_at'], true, true)) ?></span></td>
          <td data-label="Kehadiran">
            <?php if ($r['checked_in_at']): ?><span class="badge badge-success badge-dot" title="<?= e(date_id($r['checked_in_at'])) ?>">Hadir <?= e(date('H:i', strtotime($r['checked_in_at']))) ?></span>
            <?php else: ?><span class="badge badge-dot">Belum</span><?php endif; ?>
          </td>
          <td class="td-actions">
            <div class="flex gap-1" style="justify-content:flex-end">
              <form method="post" action="<?= e(route('admin.registrations.checkin', ['id' => $r['id']])) ?>">
                <?= csrf_field() ?>
                <?php if ($r['checked_in_at']): ?>
                  <button class="btn btn-sm btn-ghost" type="submit" title="Batalkan check-in" data-confirm="Batalkan check-in <?= e($r['name']) ?>?"><?= icon('x-circle') ?></button>
                <?php else: ?>
                  <button class="btn btn-sm btn-soft" type="submit" title="Tandai hadir"><?= icon('check') ?> Hadir</button>
                <?php endif; ?>
              </form>
              <a class="btn btn-sm btn-ghost" href="<?= e(route('admin.registrations.show', ['id' => $r['id']])) ?>" title="Detail"><?= icon('eye') ?></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer flex justify-between items-center wrap gap-1">
    <span class="muted small">Menampilkan <?= number_id($list->from()) ?>–<?= number_id($list->to()) ?> dari <?= number_id($list->total) ?> peserta</span>
    <?= $this->partial('partials.pagination', ['p' => $list]) ?>
  </div>
  <?php endif; ?>
</div>
