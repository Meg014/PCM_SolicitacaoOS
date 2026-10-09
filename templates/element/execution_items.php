<?php
$this->Html->script('request-form', ['block' => true, 'defer' => true]);
$raw = $this->getRequest()->getData('items');
$rows = is_array($raw) ? array_slice(array_values($raw), 0, 50) : [];
if (!$rows) { $rows = [[]]; }
$text = fn($v) => is_scalar($v) ? (string)$v : '';
?>
<div id="request-items">
<?php foreach ($rows as $index => $row): $row = is_array($row) ? $row : []; ?>
<div class="request-item"><h3 class="item-title">Apontamento <?= $index + 1 ?></h3>
<div class="item-fields">
<?php foreach (['executor' => ['Executante', 'text'], 'service_date' => ['Data do serviço', 'date'], 'start_time' => ['Hora inicial', 'time'], 'end_time' => ['Hora final', 'time']] as $field => [$label, $type]): ?>
<div><label for="item-<?= $index ?>-<?= h($field) ?>"><?= h($label) ?></label><input id="item-<?= $index ?>-<?= h($field) ?>" name="items[<?= $index ?>][<?= h($field) ?>]" type="<?= h($type) ?>" value="<?= h($text($row[$field] ?? '')) ?>" data-item-field="<?= h($field) ?>" required <?= $field === 'executor' ? 'maxlength="150"' : '' ?>></div>
<?php endforeach; ?></div>
<label for="item-<?= $index ?>">Descrição do serviço realizado</label><textarea id="item-<?= $index ?>" name="items[<?= $index ?>][description]" rows="3" maxlength="2000" required><?= h($text($row['description'] ?? '')) ?></textarea>
<button type="button" class="secondary remove-item">Remover apontamento</button>
</div>
<?php endforeach; ?>
</div>
<button type="button" id="add-item" class="secondary">+ Adicionar apontamento</button><p id="items-message" class="help" aria-live="polite"></p>