<?php
$this->extend('layouts.public');
$title = 'Tiket ' . $reg['name'];
$themeKey = $reg['theme'];
$ticketUrl = full_url('t/' . $reg['code']);
$successMsg = trim((string) ($reg['success_message'] ?? ''));
$redirect = (int) ($reg['redirect_seconds'] ?? 0);
?>
<div class="container-sm">
  <div class="ticket-wrap">
    <?php if ($isNew): ?>
      <div class="success-head no-print" data-confetti>
        <div class="check-anim"><?= icon('check') ?></div>
        <h1 style="font-size:1.6rem">Pendaftaran berhasil!</h1>
        <p class="text-2 mb-0">Terima kasih, <b><?= e($reg['name']) ?></b>. <?= $successMsg !== '' ? e($successMsg) : 'Simpan tiket ini dan tunjukkan QR code saat registrasi ulang di lokasi.' ?></p>
      </div>
    <?php endif; ?>

    <?php if ($groupUrl !== ''): ?>
      <div class="no-print mb-2">
        <a class="btn btn-wa btn-lg btn-block" href="<?= e($groupUrl) ?>" target="_blank" rel="noopener noreferrer" <?= $isNew && $redirect > 0 ? 'data-autoredirect="' . $redirect . '"' : '' ?>>
          <?= icon('whatsapp', 'icon icon-lg') ?> Gabung Grup WhatsApp
        </a>
        <?php if ($isNew && $redirect > 0): ?><div class="countdown text-center" data-countdown-label>Membuka grup otomatis dalam <b data-countdown><?= $redirect ?></b> detik…</div><?php endif; ?>
      </div>
    <?php endif; ?>

    <article class="ticket" id="ticket">
      <div class="ticket-top">
        <div class="label-sm">E-Tiket Peserta</div>
        <h2><?= e($reg['event_title']) ?></h2>
        <div class="t-meta">
          <?php if ($reg['starts_at']): ?><div><?= icon('calendar') ?><?= e(day_id($reg['starts_at']) . ', ' . date_id($reg['starts_at'])) ?></div><?php endif; ?>
          <?php if ($reg['location']): ?><div><?= icon('map-pin') ?><?= e($reg['location']) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="ticket-sep"><i></i></div>
      <div class="ticket-body">
        <div class="qr-box" data-qr="<?= e($ticketUrl) ?>" aria-label="QR code tiket"></div>
        <div class="ticket-code"><?= e($reg['code']) ?></div>
        <div class="ticket-name"><?= e($reg['name']) ?></div>
        <?php if ($reg['representative']): ?><div class="muted"><?= e($reg['representative']) ?></div><?php endif; ?>
        <div class="mt-1">
          <?php if ($reg['checked_in_at']): ?>
            <span class="badge badge-success badge-dot">Sudah hadir · <?= e(date_id($reg['checked_in_at'])) ?></span>
          <?php else: ?>
            <span class="badge badge-primary badge-dot">Terdaftar · <?= e(date_id($reg['created_at'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </article>

    <div class="ticket-actions no-print">
      <div class="row">
        <button type="button" class="btn btn-soft" data-download-qr="tiket-<?= e($reg['code']) ?>.png" data-ticket-name="<?= e($reg['name']) ?>" data-ticket-event="<?= e($reg['event_title']) ?>" data-ticket-code="<?= e($reg['code']) ?>"><?= icon('download') ?> Simpan</button>
        <button type="button" class="btn btn-soft" data-print><?= icon('printer') ?> Cetak</button>
        <button type="button" class="btn btn-soft" data-share="<?= e($ticketUrl) ?>" data-share-title="<?= e($reg['event_title']) ?>"><?= icon('share') ?> Bagikan</button>
      </div>
      <?php if ($reg['starts_at']): ?>
        <a class="btn btn-ghost" href="<?= e(route('event.ics', ['slug' => $reg['event_slug']])) ?>"><?= icon('calendar-plus') ?> Tambahkan ke kalender</a>
      <?php endif; ?>
      <p class="muted text-center small mb-0"><?= icon('info') ?> Simpan tautan halaman ini atau screenshot tiket untuk ditunjukkan saat check-in.</p>
    </div>
  </div>
</div>
