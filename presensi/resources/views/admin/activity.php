<?php
$this->extend('layouts.admin');
$title = 'Log Aktivitas';
$crumb = 'Jejak audit semua tindakan penting';
$colors = ['login' => 'badge-success', 'login_failed' => 'badge-danger', 'logout' => '', 'export' => 'badge-info',
           'checkin' => 'badge-success', 'checkin_undo' => 'badge-warning'];
?>
<div class="card">
  <form method="get" action="<?= e(route('admin.activity')) ?>" class="toolbar" data-autosubmit>
    <select class="select select-sm" name="action" aria-label="Jenis aktivitas">
      <option value="">Semua aktivitas</option>
      <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
    <span class="muted small"><?= number_id($logs->total) ?> catatan · disimpan 180 hari</span>
  </form>
  <?php if (!$logs->items): ?><div class="empty"><div class="empty-icon"><?= icon('history') ?></div><h3>Belum ada aktivitas</h3></div><?php else: ?>
  <div class="table-wrap">
    <table class="table table-cards">
      <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Keterangan</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($logs->items as $l): ?>
        <tr>
          <td data-label="Waktu"><span class="small nowrap" title="<?= e(date_id($l['created_at'])) ?>"><?= e(date_id($l['created_at'], true, true)) ?></span></td>
          <td data-label="Pengguna"><?= e($l['user_name'] ?? '—') ?></td>
          <td data-label="Aktivitas"><span class="badge <?= $colors[$l['action']] ?? 'badge-primary' ?>"><?= e($l['action']) ?></span></td>
          <td data-label="Keterangan"><?= e($l['description']) ?></td>
          <td data-label="IP" class="mono small muted"><?= e($l['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer"><?= $this->partial('partials.pagination', ['p' => $logs]) ?></div>
  <?php endif; ?>
</div>
