<?php
$this->extend('layouts.admin');
$title = 'Edit Peserta';
$crumb = $reg['name'] . ' · ' . $reg['code'];
$v = static function (string $k) use ($reg) { $o = old($k, null); return $o !== null ? $o : ($reg[$k] ?? ''); };
$oldExtra = old('extra', null);
$extraVals = is_array($oldExtra) ? $oldExtra : $extra;
?>
<?php $this->section('actions') ?>
  <a class="btn btn-ghost" href="<?= e(route('admin.registrations.show', ['id' => $reg['id']])) ?>"><?= icon('arrow-left') ?><span class="hide-sm">Batal</span></a>
<?php $this->end() ?>
<form method="post" action="<?= e(route('admin.registrations.update', ['id' => $reg['id']])) ?>" class="card" style="max-width:760px" data-loading-form>
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="form-grid cols-2">
      <div class="form-group"><label class="label" for="name">Nama <span class="req">*</span></label><input id="name" class="input <?= error('name') ? 'is-invalid' : '' ?>" name="name" value="<?= e($v('name')) ?>" required maxlength="120"><?= $this->partial('partials.field-error', ['field' => 'name']) ?></div>
      <div class="form-group"><label class="label" for="wa">WhatsApp <span class="req">*</span></label><input id="wa" class="input <?= error('wa') ? 'is-invalid' : '' ?>" name="wa" value="<?= e($v('wa')) ?>" required maxlength="25"><?= $this->partial('partials.field-error', ['field' => 'wa']) ?></div>
      <div class="form-group"><label class="label" for="email">Email</label><input id="email" class="input <?= error('email') ? 'is-invalid' : '' ?>" name="email" value="<?= e($v('email')) ?>" maxlength="150"><?= $this->partial('partials.field-error', ['field' => 'email']) ?></div>
      <div class="form-group"><label class="label" for="representative"><?= e(App\Models\Event::representativeLabel($reg)) ?></label><input id="representative" class="input" name="representative" value="<?= e($v('representative')) ?>" maxlength="150"></div>
      <div class="form-group span-2"><label class="label" for="address">Alamat</label><input id="address" class="input" name="address" value="<?= e($v('address')) ?>" maxlength="255"></div>
      <div class="form-group span-2"><label class="label" for="event_id">Event</label>
        <select id="event_id" class="select" name="event_id">
          <?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>" <?= (int) $v('event_id') === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['title']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php if ($fields): ?>
      <div class="form-section-title">Informasi tambahan</div>
      <?php foreach ($fields as $f): ?>
        <?= $this->partial('public.field', ['f' => array_merge($f, ['required' => false]), 'value' => $extraVals[$f['key']] ?? ($f['type'] === 'checkbox' ? [] : '')]) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="card-footer flex gap-1" style="justify-content:flex-end">
    <button class="btn btn-primary" type="submit"><span class="spinner"></span><?= icon('save') ?> Simpan</button>
  </div>
</form>
