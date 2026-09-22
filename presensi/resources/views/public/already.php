<?php
$this->extend('layouts.public');
$title = 'Sudah terdaftar';
$themeKey = $event['theme'];
$groupUrl = safe_url($event['group_link'] ?? '');
?>
<div class="container-sm">
  <div class="card form-card mt-3">
    <div class="card-body closed-box">
      <div class="big-icon" style="background:var(--info-soft);color:var(--info)"><?= icon('user-check') ?></div>
      <h2>Nomor ini sudah terdaftar</h2>
      <p class="text-2">Nomor WhatsApp tersebut sudah terdaftar atas nama <b><?= e($dup['name']) ?></b> pada <?= e(date_id($dup['created_at'])) ?> untuk event <b><?= e($event['title']) ?></b>.
        Anda tidak perlu mendaftar ulang — cukup sebutkan nama atau nomor WhatsApp saat registrasi ulang di lokasi.</p>
      <div class="flex gap-1 wrap" style="justify-content:center">
        <?php if ($groupUrl !== ''): ?>
          <a class="btn btn-wa" href="<?= e($groupUrl) ?>" target="_blank" rel="noopener noreferrer"><?= icon('whatsapp') ?> Gabung Grup WhatsApp</a>
        <?php endif; ?>
        <a class="btn btn-soft" href="<?= e(route('event.show', ['slug' => $event['slug']])) ?>"><?= icon('arrow-left') ?> Kembali</a>
      </div>
    </div>
  </div>
</div>
