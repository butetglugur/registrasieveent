<?php
use App\Services\Notifier;

$this->extend('layouts.admin');
$title = 'Notifikasi Peserta';
$crumb = 'Kirim pesan otomatis setelah peserta mendaftar';
$v = static function (string $k, string $default = '') { $o = old($k, null); return $o !== null ? $o : (string) setting($k, $default); };
$on = static function (string $k) use ($v) { return $v($k, '0') === '1' ? 'checked' : ''; };
$statusBadge = ['pending' => 'badge-warning', 'sending' => 'badge-info', 'sent' => 'badge-success', 'failed' => 'badge-danger', 'cancelled' => ''];
$chanIcon = ['whatsapp' => 'whatsapp', 'email' => 'mail', 'webhook' => 'link'];
$secretHint = static fn(bool $has) => $has ? '•••••••• tersimpan (terenkripsi). Kosongkan untuk tidak mengubah.' : 'Belum diisi.';
?>
<div class="alert alert-warning">
  <?= icon('alert') ?>
  <div>
    <b>Perhatian — integrasi sistem eksternal.</b> Jika diaktifkan, aplikasi akan <b>mengirim data peserta</b>
    (nama, nomor WhatsApp, email, kode tiket) ke layanan pihak ketiga yang Anda pilih: WA gateway (Fonnte/Wablas),
    server email (SMTP), dan/atau URL webhook. Pastikan layanan tersebut tepercaya, peserta telah menyetujui dihubungi,
    dan biaya/kuota pesan di akun gateway mencukupi. WA gateway tidak resmi berisiko nomor pengirim diblokir WhatsApp
    bila mengirim massal.
  </div>
</div>

<div class="stats">
  <?php foreach (['sent' => ['Terkirim', 'c-emerald', 'check-circle'], 'pending' => ['Antre / dicoba ulang', 'c-amber', 'clock'], 'failed' => ['Gagal', 'c-pink', 'x-circle']] as $k => [$l, $c, $i]): ?>
    <div class="card stat"><div class="stat-icon <?= $c ?>"><?= icon($i) ?></div><div><div class="value"><?= number_id($counts[$k] + ($k === 'pending' ? $counts['sending'] : 0)) ?></div><div class="label-s"><?= $l ?></div></div></div>
  <?php endforeach; ?>
</div>

<form method="post" action="<?= e(route('admin.notifications.update')) ?>" data-loading-form data-cond-form>
  <?= csrf_field() ?>
  <div class="card mb-2">
    <div class="card-body flex items-center justify-between wrap gap-2">
      <div>
        <h3 class="mb-0"><?= icon('zap') ?> Notifikasi otomatis</h3>
        <p class="muted small mb-0">Saklar utama. Bila mati, tidak ada data yang dikirim ke mana pun.</p>
      </div>
      <label class="switch"><input type="checkbox" name="notify_enabled" value="1" <?= $on('notify_enabled') ?>> Aktifkan notifikasi</label>
    </div>
  </div>

  <div class="grid-2 even">
    <div class="stack">
      <div class="card">
        <div class="card-header"><h3><?= icon('whatsapp') ?> WhatsApp</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="label">Provider WA gateway</label>
            <?php foreach (Notifier::WA_PROVIDERS as $k => $l): ?>
              <label class="choice"><input type="radio" name="notify_wa_provider" value="<?= e($k) ?>" <?= $v('notify_wa_provider', 'none') === $k ? 'checked' : '' ?>><span><?= e($l) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div data-show-if="notify_wa_provider=fonnte,wablas">
            <div class="form-group" data-show-if="notify_wa_provider=wablas">
              <label class="label" for="notify_wablas_domain">Domain server Wablas</label>
              <input id="notify_wablas_domain" class="input <?= error('notify_wablas_domain') ? 'is-invalid' : '' ?>" name="notify_wablas_domain" value="<?= e($v('notify_wablas_domain')) ?>" placeholder="https://jkt.wablas.com">
              <div class="hint">Lihat di dashboard Wablas Anda (tiap akun bisa beda server).</div>
              <?= $this->partial('partials.field-error', ['field' => 'notify_wablas_domain']) ?>
            </div>
            <div class="form-group">
              <label class="label" for="notify_wa_token">Token API</label>
              <input id="notify_wa_token" type="password" class="input <?= error('notify_wa_token') ? 'is-invalid' : '' ?>" name="notify_wa_token" autocomplete="new-password" placeholder="<?= $hasSecret['notify_wa_token'] ? '••••••••' : 'Tempel token dari dashboard gateway' ?>">
              <div class="hint"><?= e($secretHint($hasSecret['notify_wa_token'])) ?></div>
              <?php if ($hasSecret['notify_wa_token']): ?><label class="switch mt-1" style="font-size:.8rem;font-weight:500"><input type="checkbox" name="clear_notify_wa_token" value="1"> Hapus token tersimpan</label><?php endif; ?>
              <?= $this->partial('partials.field-error', ['field' => 'notify_wa_token']) ?>
            </div>
            <div class="form-group mb-0">
              <label class="label" for="notify_wa_template">Template pesan WhatsApp</label>
              <textarea id="notify_wa_template" class="textarea mono" name="notify_wa_template" rows="10" maxlength="2000"><?= e($v('notify_wa_template', Notifier::DEFAULT_WA_TEMPLATE)) ?></textarea>
              <div class="hint">Format WhatsApp: *tebal*, _miring_. Baris berlabel yang isinya kosong (mis. tanpa link grup) otomatis dihilangkan.</div>
              <?= $this->partial('partials.field-error', ['field' => 'notify_wa_template']) ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><?= icon('calendar') ?> Pengingat H-1</h3><label class="switch"><input type="checkbox" name="notify_reminder_enabled" value="1" <?= $on('notify_reminder_enabled') ?>> Aktif</label></div>
        <div class="card-body">
          <p class="muted small">Dikirim <b>sehari sebelum acara</b> lewat kanal yang aktif (WhatsApp/email), hanya untuk peserta yang mendaftar <b>lebih dari 1 hari</b> sebelum acara dimulai. Tiap peserta hanya sekali; tetap mengikuti jeda anti-blokir, dan otomatis dibatalkan bila belum terkirim saat acara dimulai.</p>
          <div data-show-if="notify_reminder_enabled=1">
            <div class="form-group"><label class="label" for="notify_reminder_time">Jam kirim (H-1)</label>
              <input id="notify_reminder_time" type="time" class="input <?= error('notify_reminder_time') ? 'is-invalid' : '' ?>" name="notify_reminder_time" value="<?= e($v('notify_reminder_time', App\Services\Reminder::DEFAULT_TIME)) ?>" style="max-width:180px">
              <div class="hint">Pilih jam kerja (mis. 08:00–10:00) agar pesan dibaca & tidak terasa spam.</div>
              <?= $this->partial('partials.field-error', ['field' => 'notify_reminder_time']) ?></div>
            <div class="form-group"><label class="label" for="notify_reminder_wa_template">Template pengingat WhatsApp</label>
              <textarea id="notify_reminder_wa_template" class="textarea mono" name="notify_reminder_wa_template" rows="8" maxlength="2000"><?= e($v('notify_reminder_wa_template', App\Services\Reminder::DEFAULT_WA_TEMPLATE)) ?></textarea></div>
            <div class="form-group"><label class="label" for="notify_reminder_email_subject">Subjek email pengingat</label>
              <input id="notify_reminder_email_subject" class="input" name="notify_reminder_email_subject" value="<?= e($v('notify_reminder_email_subject', App\Services\Reminder::DEFAULT_EMAIL_SUBJECT)) ?>" maxlength="200"></div>
            <div class="form-group mb-0"><label class="label" for="notify_reminder_email_template">Isi email pengingat</label>
              <textarea id="notify_reminder_email_template" class="textarea mono" name="notify_reminder_email_template" rows="6" maxlength="4000"><?= e($v('notify_reminder_email_template', App\Services\Reminder::DEFAULT_EMAIL_TEMPLATE)) ?></textarea></div>
          </div>
          <?php if ($reminders): ?>
            <div class="form-section-title mt-2">Jadwal pengingat 7 hari ke depan</div>
            <div class="list">
              <?php foreach ($reminders as $rm): ?>
                <div class="list-item" style="padding:.6rem 0">
                  <div class="grow">
                    <div class="t"><?= e($rm['event']['title']) ?></div>
                    <div class="s">Acara <?= e(date_id($rm['event']['starts_at'])) ?> ·
                      <?php if (!(int) $rm['event']['send_reminder']): ?>pengingat dimatikan untuk event ini
                      <?php else: ?>kirim <?= e(date_id(date('Y-m-d H:i:s', $rm['remind_at']))) ?> · <?= number_id($rm['eligible']) ?> menunggu<?= $rm['done'] ? ', ' . number_id($rm['done']) . ' sudah diantrekan' : '' ?><?= $rm['eligible'] ? ' · ' . e(App\Services\WaThrottle::humanDuration($rm['eta'])) : '' ?><?php endif; ?></div>
                    <?php if ($rm['late'] && (int) $rm['event']['send_reminder']): ?><div class="small text-danger"><?= icon('alert') ?> Estimasi pengiriman melewati jam acara — majukan jam kirim atau naikkan batas per jam dengan hati-hati.</div><?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><?= icon('hash') ?> Placeholder</h3></div>
        <div class="card-body">
          <div class="flex wrap gap-1">
            <?php foreach (Notifier::PLACEHOLDERS as $ph => $desc): ?>
              <button type="button" class="badge badge-primary" style="cursor:pointer;border:0" data-copy="<?= e($ph) ?>" title="<?= e($desc) ?> — klik untuk salin"><?= e($ph) ?></button>
            <?php endforeach; ?>
          </div>
          <div class="form-section-title mt-2">Pratinjau (template tersimpan)</div>
          <pre class="code" style="white-space:pre-wrap"><?= e($preview) ?></pre>
        </div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-header"><h3><?= icon('mail') ?> Email</h3><label class="switch"><input type="checkbox" name="notify_email_enabled" value="1" <?= $on('notify_email_enabled') ?>> Aktif</label></div>
        <div class="card-body" data-show-if="notify_email_enabled=1">
          <p class="muted small">Hanya terkirim bila event menampilkan kolom <b>Email</b> dan peserta mengisinya.</p>
          <div class="form-group">
            <label class="label">Metode kirim</label>
            <label class="choice"><input type="radio" name="notify_email_driver" value="smtp" <?= $v('notify_email_driver', 'smtp') === 'smtp' ? 'checked' : '' ?>><span><b>SMTP</b> <span class="muted small">— disarankan (akun email hosting / Gmail app password)</span></span></label>
            <label class="choice"><input type="radio" name="notify_email_driver" value="mail" <?= $v('notify_email_driver', 'smtp') === 'mail' ? 'checked' : '' ?>><span><b>mail() PHP</b> <span class="muted small">— sering masuk spam</span></span></label>
          </div>
          <div data-show-if="notify_email_driver=smtp">
            <div class="form-grid cols-2">
              <div class="form-group"><label class="label" for="notify_smtp_host">Host SMTP</label><input id="notify_smtp_host" class="input <?= error('notify_smtp_host') ? 'is-invalid' : '' ?>" name="notify_smtp_host" value="<?= e($v('notify_smtp_host')) ?>" placeholder="mail.domainanda.com"><?= $this->partial('partials.field-error', ['field' => 'notify_smtp_host']) ?></div>
              <div class="form-group"><label class="label" for="notify_smtp_port">Port</label><input id="notify_smtp_port" class="input <?= error('notify_smtp_port') ? 'is-invalid' : '' ?>" name="notify_smtp_port" value="<?= e($v('notify_smtp_port', '587')) ?>" inputmode="numeric"><?= $this->partial('partials.field-error', ['field' => 'notify_smtp_port']) ?></div>
              <div class="form-group"><label class="label" for="notify_smtp_encryption">Enkripsi</label>
                <select id="notify_smtp_encryption" class="select" name="notify_smtp_encryption">
                  <?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'Tanpa enkripsi'] as $k => $l): ?><option value="<?= $k ?>" <?= $v('notify_smtp_encryption', 'tls') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                </select></div>
              <div class="form-group"><label class="label" for="notify_smtp_username">Username</label><input id="notify_smtp_username" class="input" name="notify_smtp_username" value="<?= e($v('notify_smtp_username')) ?>" autocomplete="off"></div>
              <div class="form-group span-2"><label class="label" for="notify_smtp_password">Password SMTP</label><input id="notify_smtp_password" type="password" class="input" name="notify_smtp_password" autocomplete="new-password" placeholder="<?= $hasSecret['notify_smtp_password'] ? '••••••••' : '' ?>"><div class="hint"><?= e($secretHint($hasSecret['notify_smtp_password'])) ?></div>
                <?php if ($hasSecret['notify_smtp_password']): ?><label class="switch mt-1" style="font-size:.8rem;font-weight:500"><input type="checkbox" name="clear_notify_smtp_password" value="1"> Hapus password tersimpan</label><?php endif; ?></div>
            </div>
          </div>
          <div class="form-grid cols-2">
            <div class="form-group"><label class="label" for="notify_mail_from">Email pengirim</label><input id="notify_mail_from" class="input <?= error('notify_mail_from') ? 'is-invalid' : '' ?>" name="notify_mail_from" value="<?= e($v('notify_mail_from')) ?>" placeholder="noreply@domainanda.com"><?= $this->partial('partials.field-error', ['field' => 'notify_mail_from']) ?></div>
            <div class="form-group"><label class="label" for="notify_mail_from_name">Nama pengirim</label><input id="notify_mail_from_name" class="input" name="notify_mail_from_name" value="<?= e($v('notify_mail_from_name')) ?>" placeholder="<?= e(app_name()) ?>"></div>
          </div>
          <div class="form-group"><label class="label" for="notify_email_subject">Subjek</label><input id="notify_email_subject" class="input" name="notify_email_subject" value="<?= e($v('notify_email_subject', Notifier::DEFAULT_EMAIL_SUBJECT)) ?>" maxlength="200"></div>
          <div class="form-group mb-0"><label class="label" for="notify_email_template">Isi email</label><textarea id="notify_email_template" class="textarea mono" name="notify_email_template" rows="8" maxlength="4000"><?= e($v('notify_email_template', Notifier::DEFAULT_EMAIL_TEMPLATE)) ?></textarea></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><?= icon('link') ?> Webhook (integrasi sistem lain)</h3><label class="switch"><input type="checkbox" name="notify_webhook_enabled" value="1" <?= $on('notify_webhook_enabled') ?>> Aktif</label></div>
        <div class="card-body" data-show-if="notify_webhook_enabled=1">
          <p class="muted small">Setiap pendaftaran baru dikirim sebagai <code>POST</code> JSON ke URL Anda (mis. Google Apps Script, Make/Zapier, CRM, WA gateway sendiri).</p>
          <div class="form-group"><label class="label" for="notify_webhook_url">URL webhook (https)</label><input id="notify_webhook_url" class="input <?= error('notify_webhook_url') ? 'is-invalid' : '' ?>" name="notify_webhook_url" value="<?= e($v('notify_webhook_url')) ?>" placeholder="https://contoh.com/webhook/presensi"><?= $this->partial('partials.field-error', ['field' => 'notify_webhook_url']) ?></div>
          <div class="form-group mb-0"><label class="label" for="notify_webhook_secret">Secret penandatangan (opsional)</label><input id="notify_webhook_secret" type="password" class="input" name="notify_webhook_secret" autocomplete="new-password" placeholder="<?= $hasSecret['notify_webhook_secret'] ? '••••••••' : '' ?>">
            <div class="hint">Header <code>X-Presensi-Signature: sha256=HMAC(body, secret)</code> untuk memverifikasi pengirim. <?= e($secretHint($hasSecret['notify_webhook_secret'])) ?></div>
            <?php if ($hasSecret['notify_webhook_secret']): ?><label class="switch mt-1" style="font-size:.8rem;font-weight:500"><input type="checkbox" name="clear_notify_webhook_secret" value="1"> Hapus secret tersimpan</label><?php endif; ?></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><?= icon('shield') ?> Anti-blokir WhatsApp</h3><span class="badge badge-success">Selalu aktif</span></div>
        <div class="card-body">
          <p class="muted small">Pesan WA dikirim <b>satu per satu</b> dengan jeda acak, istirahat berkala, dan batas harian — bukan sekaligus. Mengurangi (tidak menghilangkan) risiko nomor diblokir.</p>
          <div class="form-grid cols-2">
            <div class="form-group"><label class="label" for="notify_wa_delay_min">Jeda min. antarpesan (detik)</label><input id="notify_wa_delay_min" class="input <?= error('notify_wa_delay_min') ? 'is-invalid' : '' ?>" name="notify_wa_delay_min" value="<?= e($v('notify_wa_delay_min', '20')) ?>" inputmode="numeric"><?= $this->partial('partials.field-error', ['field' => 'notify_wa_delay_min']) ?></div>
            <div class="form-group"><label class="label" for="notify_wa_delay_max">Jeda maks. (detik)</label><input id="notify_wa_delay_max" class="input <?= error('notify_wa_delay_max') ? 'is-invalid' : '' ?>" name="notify_wa_delay_max" value="<?= e($v('notify_wa_delay_max', '45')) ?>" inputmode="numeric"><?= $this->partial('partials.field-error', ['field' => 'notify_wa_delay_max']) ?></div>
            <div class="form-group"><label class="label" for="notify_wa_batch_size">Istirahat setiap … pesan</label><input id="notify_wa_batch_size" class="input <?= error('notify_wa_batch_size') ? 'is-invalid' : '' ?>" name="notify_wa_batch_size" value="<?= e($v('notify_wa_batch_size', '15')) ?>" inputmode="numeric"><?= $this->partial('partials.field-error', ['field' => 'notify_wa_batch_size']) ?></div>
            <div class="form-group"><label class="label" for="notify_wa_batch_rest">Lama istirahat (menit)</label><input id="notify_wa_batch_rest" class="input <?= error('notify_wa_batch_rest') ? 'is-invalid' : '' ?>" name="notify_wa_batch_rest" value="<?= e($v('notify_wa_batch_rest', '5')) ?>" inputmode="numeric"><?= $this->partial('partials.field-error', ['field' => 'notify_wa_batch_rest']) ?></div>
            <div class="form-group"><label class="label" for="notify_wa_hourly_limit">Maks. pesan per jam</label><input id="notify_wa_hourly_limit" class="input <?= error('notify_wa_hourly_limit') ? 'is-invalid' : '' ?>" name="notify_wa_hourly_limit" value="<?= e($v('notify_wa_hourly_limit', '60')) ?>" inputmode="numeric"><?= $this->partial('partials.field-error', ['field' => 'notify_wa_hourly_limit']) ?></div>
            <div class="form-group"><label class="label" for="notify_wa_daily_limit">Maks. pesan per hari</label><input id="notify_wa_daily_limit" class="input <?= error('notify_wa_daily_limit') ? 'is-invalid' : '' ?>" name="notify_wa_daily_limit" value="<?= e($v('notify_wa_daily_limit', '300')) ?>" inputmode="numeric"><?= $this->partial('partials.field-error', ['field' => 'notify_wa_daily_limit']) ?></div>
          </div>
          <label class="switch mb-1"><input type="checkbox" name="notify_quiet_enabled" value="1" <?= $on('notify_quiet_enabled') ?>> Jam tenang (tidak mengirim WA)</label>
          <div class="form-grid cols-2" data-show-if="notify_quiet_enabled=1">
            <div class="form-group"><label class="label" for="notify_quiet_start">Mulai</label><input id="notify_quiet_start" type="time" class="input <?= error('notify_quiet_start') ? 'is-invalid' : '' ?>" name="notify_quiet_start" value="<?= e($v('notify_quiet_start', '21:00')) ?>"><?= $this->partial('partials.field-error', ['field' => 'notify_quiet_start']) ?></div>
            <div class="form-group"><label class="label" for="notify_quiet_end">Selesai</label><input id="notify_quiet_end" type="time" class="input <?= error('notify_quiet_end') ? 'is-invalid' : '' ?>" name="notify_quiet_end" value="<?= e($v('notify_quiet_end', '07:00')) ?>"><?= $this->partial('partials.field-error', ['field' => 'notify_quiet_end']) ?></div>
          </div>
          <div class="hint">Saran nomor baru: jeda 30–60 dtk, 30/jam, 150/hari. Variasikan kalimat dengan <code>{Halo|Hai}</code> di template.</div>
        </div>
      </div>

      <button class="btn btn-primary btn-lg btn-block" type="submit"><span class="spinner"></span><?= icon('save') ?> Simpan pengaturan notifikasi</button>
    </div>
  </div>
</form>

<div class="grid-2 even mt-3">
  <form method="post" action="<?= e(route('admin.notifications.test')) ?>" class="card" data-loading-form>
    <?= csrf_field() ?>
    <div class="card-header"><h3><?= icon('zap') ?> Kirim tes</h3></div>
    <div class="card-body">
      <p class="muted small">Simpan pengaturan dulu, lalu kirim pesan tes berisi data contoh. ⚠️ Pesan tes benar-benar dikirim lewat layanan eksternal.</p>
      <div class="form-grid cols-2">
        <div class="form-group"><label class="label" for="t-channel">Kanal</label>
          <select id="t-channel" class="select" name="channel"><option value="whatsapp">WhatsApp</option><option value="email">Email</option><option value="webhook">Webhook</option></select></div>
        <div class="form-group"><label class="label" for="t-target">Tujuan</label><input id="t-target" class="input" name="target" placeholder="08xx / email@contoh.com"></div>
      </div>
      <button class="btn btn-soft" type="submit"><span class="spinner"></span><?= icon('arrow-right') ?> Kirim tes</button>
    </div>
  </form>
  <div class="card" data-queue-poll="<?= e(route('admin.notifications.process')) ?>" data-wa-pending="<?= (int) $waPending ?>">
    <div class="card-header"><h3><?= icon('clock') ?> Antrean & cron</h3>
      <a class="btn btn-sm btn-soft" href="<?= e(route('admin.broadcast')) ?>"><?= icon('users') ?> Pesan massal</a></div>
    <div class="card-body small text-2">
      <p class="mb-1"><b data-q-wa><?= number_id($waPending) ?></b> WhatsApp antre.
        <?php if ($waNextAt > time()): ?>Pesan berikutnya boleh dikirim pukul <b><?= e(date('H:i:s', $waNextAt)) ?></b>.<?php endif; ?>
        <span data-q-status></span></p>
      <?php if ($waPending > 0): ?>
        <form method="post" action="<?= e(route('admin.notifications.cancel')) ?>" data-confirm="Batalkan semua pesan WhatsApp yang belum terkirim?" class="mb-1">
          <?= csrf_field() ?><button class="btn btn-sm btn-danger-soft" type="submit"><?= icon('x-circle') ?> Batalkan antrean WA</button>
        </form>
        <p class="mb-1"><?= icon('info') ?> Selama halaman ini terbuka, antrean diproses otomatis (sesuai jeda).</p>
      <?php endif; ?>
      <div class="form-section-title mt-1">Cron job (disarankan)</div>
      <p class="mb-1">Agar antrean tetap jalan walau tidak ada yang membuka situs, tambahkan cron <b>setiap menit</b> (<code>* * * * *</code>) di DirectAdmin → <i>Advanced Features → Cron Jobs</i>:</p>
      <div class="share-box mb-1"><code><?= e($cronCmd) ?></code><button type="button" class="btn btn-sm btn-ghost" data-copy="<?= e($cronCmd) ?>"><?= icon('copy') ?></button></div>
      <p class="mb-1">Atau bila perintah PHP tidak tersedia:</p>
      <div class="share-box mb-1"><code>wget -q -O /dev/null "<?= e($cronUrl) ?>"</code><button type="button" class="btn btn-sm btn-ghost" data-copy="wget -q -O /dev/null &quot;<?= e($cronUrl) ?>&quot;"><?= icon('copy') ?></button></div>
      <p class="mb-0">Cron terakhir berjalan: <b><?= $cronLast ? e(date_id(date('Y-m-d H:i:s', $cronLast)) . ' (' . time_ago(date('Y-m-d H:i:s', $cronLast)) . ')') : 'belum pernah' ?></b>. URL cron bersifat rahasia — jangan dibagikan.</p>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header">
    <h3><?= icon('history') ?> Riwayat pengiriman</h3>
    <div class="flex gap-1 wrap items-center">
      <nav class="tabs">
        <?php foreach (['' => 'Semua'] + App\Models\Notification::STATUSES as $k => $l): ?>
          <a href="<?= e(route('admin.notifications', [], ['status' => $k])) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($l) ?></a>
        <?php endforeach; ?>
      </nav>
      <form method="post" action="<?= e(route('admin.notifications.process')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= icon('refresh') ?> Proses antrean</button></form>
    </div>
  </div>
  <?php if (!$logs->items): ?>
    <div class="empty"><div class="empty-icon"><?= icon('mail') ?></div><h3>Belum ada notifikasi</h3><p>Riwayat pengiriman akan tampil di sini.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table table-cards">
      <thead><tr><th>Waktu</th><th>Jenis</th><th>Kanal</th><th>Peserta</th><th>Tujuan</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($logs->items as $n): ?>
        <tr>
          <td data-label="Waktu"><span class="small nowrap"><?= e(date_id($n['created_at'], true, true)) ?></span></td>
          <td data-label="Jenis"><span class="badge <?= ['reminder' => 'badge-info', 'broadcast' => 'badge-primary'][$n['kind'] ?? ''] ?? '' ?>"><?= e(['registration' => 'Pendaftaran', 'reminder' => 'Pengingat H-1', 'broadcast' => 'Pesan massal'][$n['kind'] ?? 'registration'] ?? $n['kind']) ?></span></td>
          <td data-label="Kanal"><span class="nowrap"><?= icon($chanIcon[$n['channel']] ?? 'zap') ?> <?= e(ucfirst((string) $n['channel'])) ?> <span class="muted small">(<?= e($n['provider']) ?>)</span></span></td>
          <td data-label="Peserta"><?= $n['reg_name'] ? e($n['reg_name']) . ' <span class="mono muted small">' . e($n['reg_code']) . '</span>' : '<span class="muted">—</span>' ?></td>
          <td data-label="Tujuan"><span class="small mono"><?= e($n['channel'] === 'whatsapp' ? mask_wa((string) $n['recipient']) : str_limit((string) $n['recipient'], 40)) ?></span></td>
          <td data-label="Status">
            <span class="badge badge-dot <?= $statusBadge[$n['status']] ?? '' ?>"><?= e(App\Models\Notification::STATUSES[$n['status']] ?? $n['status']) ?><?= (int) $n['attempts'] > 1 ? ' · ' . (int) $n['attempts'] . '×' : '' ?></span>
            <?php if ($n['last_error'] && $n['status'] !== 'sent'): ?><div class="small text-danger" style="max-width:280px"><?= e($n['last_error']) ?></div><?php endif; ?>
          </td>
          <td class="td-actions">
            <?php if (in_array($n['status'], ['failed', 'pending'], true)): ?>
              <form method="post" action="<?= e(route('admin.notifications.retry', ['id' => $n['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-soft" type="submit"><?= icon('refresh') ?> Kirim ulang</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer"><?= $this->partial('partials.pagination', ['p' => $logs]) ?></div>
  <?php endif; ?>
</div>
