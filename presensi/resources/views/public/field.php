<?php
/** Render satu field dinamis. @var array $f @var mixed $value */
$__name = 'extra[' . $f['key'] . ']';
$__id = 'x-' . $f['key'];
$__err = error('extra_' . $f['key']);
$__req = !empty($f['required']);
$__cls = $__err ? ' is-invalid' : '';
$__ph = $f['placeholder'] ?? '';
?>
<div class="form-group">
  <label class="label" <?= in_array($f['type'], ['radio', 'checkbox'], true) ? '' : 'for="' . e($__id) . '"' ?>><?= e($f['label']) ?> <?= $__req ? '<span class="req">*</span>' : '<span class="muted">(opsional)</span>' ?></label>
  <?php switch ($f['type']):
    case 'textarea': ?>
      <textarea id="<?= e($__id) ?>" class="textarea<?= $__cls ?>" name="<?= e($__name) ?>" maxlength="1000" placeholder="<?= e($__ph) ?>" <?= $__req ? 'required' : '' ?>><?= e(is_string($value) ? $value : '') ?></textarea>
    <?php break; case 'select': ?>
      <select id="<?= e($__id) ?>" class="select<?= $__cls ?>" name="<?= e($__name) ?>" <?= $__req ? 'required' : '' ?>>
        <option value="">— Pilih —</option>
        <?php foreach ($f['options'] as $o): ?><option value="<?= e($o) ?>" <?= $value === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
      </select>
    <?php break; case 'radio': ?>
      <div class="choices-inline" role="radiogroup">
        <?php foreach ($f['options'] as $i => $o): ?>
          <label class="choice"><input type="radio" name="<?= e($__name) ?>" value="<?= e($o) ?>" <?= $value === $o ? 'checked' : '' ?> <?= $__req && $i === 0 ? 'required' : '' ?>><span><?= e($o) ?></span></label>
        <?php endforeach; ?>
      </div>
    <?php break; case 'checkbox': ?>
      <div class="choices-inline">
        <?php foreach ($f['options'] as $o): ?>
          <label class="choice"><input type="checkbox" name="<?= e($__name) ?>[]" value="<?= e($o) ?>" <?= is_array($value) && in_array($o, $value, true) ? 'checked' : '' ?>><span><?= e($o) ?></span></label>
        <?php endforeach; ?>
      </div>
    <?php break; default:
      $__type = ['email' => 'email', 'number' => 'text', 'date' => 'date'][$f['type']] ?? 'text'; ?>
      <input id="<?= e($__id) ?>" type="<?= $__type ?>" class="input<?= $__cls ?>" name="<?= e($__name) ?>" value="<?= e(is_string($value) ? $value : '') ?>" placeholder="<?= e($__ph) ?>" <?= $f['type'] === 'number' ? 'inputmode="decimal"' : '' ?> maxlength="255" <?= $__req ? 'required' : '' ?>>
  <?php endswitch; ?>
  <?php if ($__err): ?><div class="invalid"><?= icon('alert') ?><?= e($__err) ?></div><?php endif; ?>
</div>
