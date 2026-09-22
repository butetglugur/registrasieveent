<?php /** @var App\Core\Paginator $p */ ?>
<?php if ($p->lastPage > 1): ?>
<nav class="pagination" aria-label="Halaman">
  <?php if ($p->page > 1): ?><a href="<?= e($p->url($p->page - 1)) ?>" aria-label="Sebelumnya"><?= icon('chevron-left') ?></a><?php endif; ?>
  <?php foreach ($p->window() as $__n): ?>
    <?php if ($__n === null): ?><span class="dots">…</span>
    <?php elseif ($__n === $p->page): ?><span class="active" aria-current="page"><?= $__n ?></span>
    <?php else: ?><a href="<?= e($p->url($__n)) ?>"><?= $__n ?></a><?php endif; ?>
  <?php endforeach; ?>
  <?php if ($p->page < $p->lastPage): ?><a href="<?= e($p->url($p->page + 1)) ?>" aria-label="Berikutnya"><?= icon('chevron-right') ?></a><?php endif; ?>
</nav>
<?php endif; ?>
