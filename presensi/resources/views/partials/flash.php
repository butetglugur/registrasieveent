<?php
$__flashMap = ['success' => ['alert-success', 'check-circle'], 'error' => ['alert-error', 'x-circle'],
               'warning' => ['alert-warning', 'alert'], 'info' => ['alert-info', 'info']];
$__msgs = [];
foreach ($__flashMap as $__type => $__cfg) {
    $__m = session()->getFlash($__type);
    if (is_string($__m) && $__m !== '') {
        $__msgs[] = [$__cfg, $__m];
    }
}
?>
<?php if ($__msgs): ?>
<div class="toasts" role="status" aria-live="polite">
  <?php foreach ($__msgs as [$__cfg, $__m]): ?>
    <div class="alert toast <?= $__cfg[0] ?>" data-toast>
      <?= icon($__cfg[1]) ?><span><?= e($__m) ?></span>
      <button type="button" class="toast-close" data-toast-close aria-label="Tutup"><?= icon('x') ?></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
