<?php
$pairData = $pairData ?? [];
$isRow = isset($rowIndex);
$prefix = $isRow ? 'items[' . $rowIndex . ']' : '';
$key = $isRow ? 'item-' . $rowIndex : 'request';
?>
<div class="service-pair" data-service-selection>
<?php foreach (['service_action_id' => ['Ação', $serviceActionOptions, 'action'], 'service_component_id' => ['Item / Componente', $serviceComponentOptions, 'component']] as $field => [$label, $options, $kind]): ?>
<div>
<label for="<?= h($key . '-' . $field) ?>"><?= h($label) ?></label>
<select id="<?= h($key . '-' . $field) ?>" name="<?= h($isRow ? $prefix . '[' . $field . ']' : $field) ?>" <?= $isRow ? 'data-item-field="' . h($field) . '"' : '' ?> data-service-<?= h($kind) ?> required>
<option value="">Selecione <?= $kind === 'action' ? 'a ação' : 'o item/componente' ?></option>
<?php foreach ($options as $id => $name): ?>
<option value="<?= h($id) ?>" <?= is_scalar($pairData[$field] ?? null) && (string)$pairData[$field] === (string)$id ? 'selected' : '' ?>><?= h($name) ?></option>
<?php endforeach; ?>
</select>
<?php if (!$isRow): ?><?= $this->Form->error($field) ?><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!$isRow): ?><output data-service-preview class="help" aria-live="polite"></output><?php endif; ?>
</div>
