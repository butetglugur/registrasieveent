<?php
$this->extend('layouts.admin');
$title = 'Detail Peserta';
$crumb = $reg['event_title'];
$ticketUrl = full_url('t/' . $reg['code']);
$fmt = static fn($v) => is_array($v) ? implode(', ', $v) : (string) $v;
?>
<?php $this->section('actions') ?>
  <a class="btn btn-ghost" href="<?= e(route('admin.registrations', [], ['event' => $reg['event_id']])) ?>"><?= icon('arrow-left') ?><span class="hide-sm">Kembali</span></a>
  <?php if (is_admin()): ?><a class="btn btn-soft" href="<?= e(route('admin.registrations.edit', ['id' => $reg['id']])) ?>"><?= icon('edit') ?><span class="hide-sm">Edit</span></a><?php endif; ?>
<?php $this->end() ?>

<div class="grid-2">
  <div class="card">
    <div class="card-body">
      <div class="person mb-2">
        <span class="avatar" style="width:56px;height:56px;font-size:1.2rem;border-radius:18px"><?= e(mb_strtoupper(mb_substr((string) $reg['name'], 0, 1))) ?></span>
        <div><h2 class="mb-0"><?= e($reg['name']) ?></h2><span class="mono muted"><?= e($reg['code']) ?></span></div>
      </div>
      <dl class="kv">
        <dt>Event</dt><dd><?= e($reg['event_title']) ?></dd>
        <dt>WhatsApp</dt><dd><a href="<?= e(wa_link((string) $reg['wa'], 'Halo ' . $reg['name'] . ', ')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> <?= e($reg['wa']) ?></a></dd>
        <?php if ($reg['email']): ?><dt>Email</dt><dd><a href="mailto:<?= e($reg['email']) ?>"><?= e($reg['email']) ?></a></dd><?php endif; ?>
        <?php if ($reg['representative']): ?><dt><?= e(App\Models\Event::representativeLabel($reg)) ?></dt><dd><?= e($reg['representative']) ?></dd><?php endif; ?>
        <?php if ($reg['address']): ?><dt>Alamat</dt><dd><?= e($reg['address']) ?></dd><?php endif; ?>
        <?php foreach ($fields as $f): ?>
          <dt><?= e($f['label']) ?></dt><dd><?= e($fmt($extra[$f['key']] ?? '')) ?: '<span class="muted">-</span>' ?></dd>
        <?php endforeach; ?>
        <?php foreach ($extra as $k => $val): if (in_array($k, array_column($fields, 'key'), true)) { continue; } ?>
          <dt><?= e($k) ?></dt><dd><?= e($fmt($val)) ?></dd>
        <?php endforeach; ?>
        <dt>Waktu daftar</dt><dd><?= e(day_id($reg['created_at']) . ', ' . date_id($reg['created_at'])) ?></dd>
        <dt>Pengingat H-1</dt><dd><?= e(App\Services\Reminder::statusFor($reg, $reg['event_starts_at'] ?? null, (bool) (int) ($reg['event_send_reminder'] ?? 1))) ?></dd>
        <dt>IP pendaftar</dt><dd class="mono muted"><?= e($reg['ip'] ?: '-') ?></dd>
      </dl>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-body text-center">
        <?php if ($reg['checked_in_at']): ?>
          <div class="scan-result ok" style="text-align:left"><div class="ic"><?= icon('check') ?></div><div><div class="t">Sudah hadir</div><div class="s"><?= e(date_id($reg['checked_in_at'])) ?><?= $checker ? ' · oleh ' . e($checker['name']) : '' ?></div></div></div>
        <?php else: ?>
          <div class="scan-result warn" style="text-align:left"><div class="ic"><?= icon('clock') ?></div><div><div class="t">Belum hadir</div><div class="s">Tandai hadir saat peserta tiba di lokasi.</div></div></div>
        <?php endif; ?>
        <form method="post" action="<?= e(route('admin.registrations.checkin', ['id' => $reg['id']])) ?>" class="mt-2">
          <?= csrf_field() ?>
          <?php if ($reg['checked_in_at']): ?>
            <button class="btn btn-ghost btn-block" type="submit" data-confirm="Batalkan status hadir <?= e($reg['name']) ?>?"><?= icon('x-circle') ?> Batalkan check-in</button>
          <?php else: ?>
            <button class="btn btn-success btn-lg btn-block" type="submit"><?= icon('user-check') ?> Tandai hadir sekarang</button>
          <?php endif; ?>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3><?= icon('qr') ?> Tiket</h3></div>
      <div class="card-body text-center">
        <div class="qr-box" data-qr="<?= e($ticketUrl) ?>" style="width:180px;height:180px"></div>
        <div class="flex gap-1 wrap" style="justify-content:center">
          <a class="btn btn-sm btn-soft" href="<?= e($ticketUrl) ?>" target="_blank" rel="noopener"><?= icon('external') ?> Buka tiket</a>
          <a class="btn btn-sm btn-wa" href="<?= e(wa_link((string) $reg['wa'], 'Halo ' . $reg['name'] . ', berikut e-tiket Anda untuk ' . $reg['event_title'] . ': ' . $ticketUrl)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> Kirim via WA</a>
        </div>
      </div>
    </div>
    <?php if (is_admin()): ?>
    <form method="post" action="<?= e(route('admin.registrations.destroy', ['id' => $reg['id']])) ?>" data-confirm="Hapus data <?= e($reg['name']) ?>? Tindakan ini tidak dapat dibatalkan.">
      <?= csrf_field() ?>
      <button class="btn btn-danger-soft btn-block" type="submit"><?= icon('trash') ?> Hapus peserta</button>
    </form>
    <?php endif; ?>
  </div>
</div>
