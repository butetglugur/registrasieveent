<?php
$this->extend('layouts.admin');
$title = 'Dasbor';
$crumb = 'Halo, ' . (auth_user()['name'] ?? '') . ' 👋';
$rate = $stats['total'] ? round($stats['checked_in'] / $stats['total'] * 100) : 0;
$max = max(1, max($daily));
$w = 700; $h = 220; $pad = 28; $n = count($daily);
$bw = ($w - $pad * 2) / max(1, $n);
?>
<?php $this->section('actions') ?>
  <?php if (is_admin()): ?><a class="btn btn-primary" href="<?= e(route('admin.events.create')) ?>"><?= icon('plus') ?><span class="hide-sm">Event baru</span></a><?php endif; ?>
<?php $this->end() ?>

<div class="stats">
  <div class="card stat"><div class="stat-icon c-violet"><?= icon('users') ?></div><div><div class="value"><?= number_id($stats['total']) ?></div><div class="label-s">Total pendaftar</div></div></div>
  <div class="card stat"><div class="stat-icon c-pink"><?= icon('user-plus') ?></div><div><div class="value"><?= number_id($stats['today']) ?></div><div class="label-s">Daftar hari ini</div></div></div>
  <div class="card stat"><div class="stat-icon c-emerald"><?= icon('user-check') ?></div><div><div class="value"><?= number_id($stats['checked_in']) ?></div><div class="label-s">Sudah hadir · <?= $rate ?>%</div></div></div>
  <div class="card stat"><div class="stat-icon c-sky"><?= icon('calendar') ?></div><div><div class="value"><?= number_id($eventCounts['open']) ?></div><div class="label-s">Event dibuka · <?= number_id(array_sum($eventCounts)) ?> total</div></div></div>
</div>

<div class="grid-2">
  <div class="stack">
    <div class="card">
      <div class="card-header"><h3><?= icon('trending') ?> Pendaftar 14 hari terakhir</h3><span class="badge badge-primary"><?= number_id(array_sum($daily)) ?> pendaftar</span></div>
      <div class="card-body">
        <svg class="chart" viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="none" role="img" aria-label="Grafik pendaftar harian">
          <defs><linearGradient id="barGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c026d3"/><stop offset="1" stop-color="#7c3aed"/></linearGradient></defs>
          <?php for ($i = 0; $i <= 3; $i++): $y = $pad + ($h - $pad * 2) * $i / 3; ?>
            <line class="grid-line" x1="<?= $pad ?>" x2="<?= $w - $pad ?>" y1="<?= $y ?>" y2="<?= $y ?>"/>
          <?php endfor; ?>
          <?php $i = 0; foreach ($daily as $d => $c):
            $bh = ($h - $pad * 2) * $c / $max; $x = $pad + $i * $bw + $bw * .18; $y = $h - $pad - $bh; ?>
            <rect class="bar" x="<?= round($x, 1) ?>" y="<?= round($y, 1) ?>" width="<?= round($bw * .64, 1) ?>" height="<?= round(max($bh, $c ? 2 : 0), 1) ?>" rx="5"><title><?= e(date_id($d, false)) ?>: <?= $c ?> pendaftar</title></rect>
            <?php if ($c): ?><text class="val" x="<?= round($x + $bw * .32, 1) ?>" y="<?= round($y - 5, 1) ?>" text-anchor="middle"><?= $c ?></text><?php endif; ?>
            <?php if ($i % 2 === 0 || $n <= 7): ?><text x="<?= round($x + $bw * .32, 1) ?>" y="<?= $h - 8 ?>" text-anchor="middle"><?= e(date('j/n', strtotime($d))) ?></text><?php endif; ?>
          <?php $i++; endforeach; ?>
        </svg>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><?= icon('calendar') ?> Event aktif</h3><?php if (is_admin()): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('admin.events')) ?>">Semua <?= icon('arrow-right') ?></a><?php endif; ?></div>
      <?php if (!$events): ?>
        <div class="empty"><div class="empty-icon"><?= icon('calendar-plus') ?></div><h3>Belum ada event</h3><p>Buat event pertama Anda untuk mulai menerima pendaftaran.</p>
          <?php if (is_admin()): ?><a class="btn btn-primary" href="<?= e(route('admin.events.create')) ?>"><?= icon('plus') ?> Buat event</a><?php endif; ?></div>
      <?php else: foreach ($events as $ev):
        $pct = $ev['total_reg'] ? round($ev['total_checkin'] / $ev['total_reg'] * 100) : 0; ?>
        <div class="event-row" style="<?= e(theme_style($ev['theme'])) ?>">
          <div class="event-thumb"><?php if ($ev['starts_at']): ?><?= date('j', strtotime($ev['starts_at'])) ?><small><?= ID_MONTHS_SHORT[(int) date('n', strtotime($ev['starts_at']))] ?></small><?php else: ?><?= icon('calendar') ?><?php endif; ?></div>
          <div class="info">
            <div class="t"><?= e($ev['title']) ?></div>
            <div class="s"><span><?= icon('users') ?><?= number_id($ev['total_reg']) ?> daftar</span><span><?= icon('user-check') ?><?= number_id($ev['total_checkin']) ?> hadir (<?= $pct ?>%)</span></div>
            <div class="progress dark"><span style="width:<?= $pct ?>%"></span></div>
          </div>
          <div class="acts">
            <a class="btn btn-sm btn-soft" href="<?= e(route('admin.registrations', [], ['event' => $ev['id']])) ?>"><?= icon('users') ?> Peserta</a>
            <a class="btn btn-sm btn-ghost" href="<?= e(route('admin.checkin', [], ['event' => $ev['id']])) ?>"><?= icon('scan') ?></a>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-header"><h3><?= icon('user-plus') ?> Pendaftar terbaru</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('admin.registrations')) ?>">Semua <?= icon('arrow-right') ?></a></div>
      <?php if (!$recent): ?><div class="empty"><p class="mb-0">Belum ada pendaftar.</p></div><?php endif; ?>
      <div class="list">
        <?php foreach ($recent as $r): ?>
          <a class="list-item" href="<?= e(route('admin.registrations.show', ['id' => $r['id']])) ?>" style="text-decoration:none;color:inherit;<?= e(theme_style($r['theme'])) ?>">
            <span class="avatar"><?= e(mb_strtoupper(mb_substr((string) $r['name'], 0, 1))) ?></span>
            <div class="grow"><div class="t"><?= e($r['name']) ?></div><div class="s"><?= e($r['event_title']) ?></div></div>
            <span class="muted small nowrap"><?= e(time_ago($r['created_at'])) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($topReps): ?>
    <div class="card">
      <div class="card-header"><h3><?= icon('building') ?> Perwakilan terbanyak</h3></div>
      <div class="card-body" style="display:grid;gap:.8rem">
        <?php $topMax = max(array_map(static fn($x) => (int) $x['c'], $topReps)); foreach ($topReps as $t): ?>
          <div>
            <div class="flex justify-between small"><b><?= e(str_limit((string) $t['representative'], 34)) ?></b><span class="muted"><?= number_id($t['c']) ?></span></div>
            <div class="progress dark" style="margin-top:.3rem"><span style="width:<?= round($t['c'] / max(1, $topMax) * 100) ?>%"></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($activity): ?>
    <div class="card">
      <div class="card-header"><h3><?= icon('activity') ?> Aktivitas terakhir</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('admin.activity')) ?>">Log <?= icon('arrow-right') ?></a></div>
      <div class="list">
        <?php foreach ($activity as $a): ?>
          <div class="list-item"><span class="dot"></span><div class="grow"><div class="t" style="font-weight:600"><?= e($a['description']) ?></div><div class="s"><?= e($a['user_name'] ?? 'Sistem') ?> · <?= e(time_ago($a['created_at'])) ?></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
