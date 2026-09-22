<?php
$this->extend('layouts.admin');
$title = 'Check-in';
$crumb = $event ? $event['title'] : 'Scan QR tiket peserta';
$rate = $stats['total'] ? round($stats['checked_in'] / $stats['total'] * 100) : 0;
?>
<div class="grid-2 even" data-checkin
     data-store-url="<?= e(route('admin.checkin.store')) ?>"
     data-search-url="<?= e(route('admin.checkin.search')) ?>"
     data-event-id="<?= (int) ($event['id'] ?? 0) ?>"
     data-jsqr="<?= e(asset('js/vendor/jsqr.min.js')) ?>">
  <div class="stack">
    <div class="card">
      <div class="card-header">
        <h3><?= icon('camera') ?> Pemindai QR</h3>
        <form method="get" action="<?= e(route('admin.checkin')) ?>" data-autosubmit>
          <select class="select select-sm" name="event" aria-label="Filter event">
            <option value="">Semua event</option>
            <?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>" <?= (int) ($event['id'] ?? 0) === (int) $ev['id'] ? 'selected' : '' ?>><?= e(str_limit((string) $ev['title'], 36)) ?></option><?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="card-body">
        <div class="scanner" data-scanner>
          <video playsinline muted hidden data-video></video>
          <div class="frame" hidden data-frame></div><div class="laser" hidden data-laser></div>
          <div class="placeholder" data-placeholder><?= icon('qr') ?><div>Arahkan kamera ke QR code tiket peserta</div></div>
        </div>
        <div class="flex gap-1 mt-2 wrap">
          <button type="button" class="btn btn-primary grow" data-scan-start><?= icon('camera') ?> Mulai kamera</button>
          <button type="button" class="btn btn-ghost" data-scan-stop hidden><?= icon('power') ?> Stop</button>
          <button type="button" class="btn btn-ghost" data-scan-flip hidden title="Ganti kamera"><?= icon('refresh') ?></button>
        </div>
        <label class="switch mt-2" style="font-size:.85rem"><input type="checkbox" data-scan-sound checked> Bunyi & getar saat berhasil</label>
      </div>
    </div>
    <div data-scan-result aria-live="assertive"></div>
  </div>

  <div class="stack">
    <div class="stats" style="margin:0">
      <div class="card stat"><div class="stat-icon c-emerald"><?= icon('user-check') ?></div><div><div class="value" data-stat-in><?= number_id($stats['checked_in']) ?></div><div class="label-s">Hadir</div></div></div>
      <div class="card stat"><div class="stat-icon c-violet"><?= icon('users') ?></div><div><div class="value" data-stat-total><?= number_id($stats['total']) ?></div><div class="label-s">Terdaftar · <span data-stat-rate><?= $rate ?></span>%</div></div></div>
    </div>
    <div class="card">
      <div class="card-header"><h3><?= icon('ticket') ?> Input kode manual</h3></div>
      <div class="card-body">
        <form class="flex gap-1" data-manual-form>
          <input class="input mono" name="code" placeholder="Kode tiket, mis. AB12CD34" maxlength="16" autocomplete="off" style="text-transform:uppercase;letter-spacing:.1em" required>
          <button class="btn btn-primary" type="submit"><?= icon('check') ?> Check-in</button>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3><?= icon('search') ?> Cari peserta</h3></div>
      <div class="card-body">
        <div class="input-icon"><?= icon('search') ?><input class="input" placeholder="Ketik nama, No. WA, atau instansi…" data-live-search autocomplete="off"></div>
        <div class="search-results" data-search-results></div>
      </div>
    </div>
  </div>
</div>
