<?php
$this->Html->script('request-form.js?v=' . filemtime(WWW_ROOT . 'js' . DS . 'request-form.js'), ['block' => true, 'defer' => true]);
$this->Html->script('employee-autocomplete.js?v=' . filemtime(WWW_ROOT . 'js' . DS . 'employee-autocomplete.js'), ['block' => true, 'defer' => true]);
$raw = $this->getRequest()->getData('items');
$rows = is_array($raw) ? array_slice(array_values($raw), 0, 50) : [[]];
$text = fn($v) => is_scalar($v) ? (string)$v : '';
$renderRow = function ($index, $row) use ($text): void {
    $row = is_array($row) ? $row : [];
?>
<div class="request-item execution-row">
<input type="hidden" name="items[<?= h($index) ?>][_added]" data-included value="<?= ($row['_added'] ?? '') === '1' ? '1' : '' ?>">
<div class="autocomplete employee-field" data-kind="employee" data-lookup="<?= $this->Url->build(['controller' => 'Lookups', 'action' => 'employees']) ?>">
<label for="item-<?= h($index) ?>-employee_label">Funcionário</label>
<input id="item-<?= h($index) ?>-employee_label" name="items[<?= h($index) ?>][employee_label]" value="<?= h($text($row['employee_label'] ?? $row['employee_registration'] ?? '')) ?>" type="text" data-item-field="employee_label" data-search role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="employees-<?= h($index) ?>" autocomplete="off" maxlength="150" required placeholder="Nome ou matrícula">
<input name="items[<?= h($index) ?>][employee_registration]" type="hidden" data-item-field="employee_registration" data-selected value="<?= h($text($row['employee_registration'] ?? '')) ?>">
<div id="employees-<?= h($index) ?>" role="listbox" aria-label="Funcionários encontrados" hidden></div>
<p class="lookup-message help" aria-live="polite"></p>
</div>
<?php foreach (['service_date' => ['Data', 'date'], 'start_time' => ['Hora início', 'time'], 'end_time' => ['Hora fim', 'time']] as $field => [$label, $type]): ?>
<div><label for="item-<?= h($index) ?>-<?= h($field) ?>"><?= h($label) ?></label><input id="item-<?= h($index) ?>-<?= h($field) ?>" name="items[<?= h($index) ?>][<?= h($field) ?>]" type="<?= h($type) ?>" value="<?= h($text($row[$field] ?? '')) ?>" data-item-field="<?= h($field) ?>" required></div>
<?php endforeach; ?>
<div><label for="item-<?= h($index) ?>-description">Serviço realizado</label><textarea id="item-<?= h($index) ?>-description" name="items[<?= h($index) ?>][description]" data-item-field="description" rows="1" maxlength="2000" required><?= h($text($row['description'] ?? '')) ?></textarea></div>
<div class="execution-actions"><span class="execution-action-label">Ações</span><button type="button" class="secondary remove-item" aria-label="Remover apontamento" title="Remover apontamento">−</button></div>
</div>
<?php } ?>
<div id="request-items" class="execution-grid">
<?php foreach ($rows as $index => $row) { $renderRow($index, $row); } ?>
</div>
<template id="item-template"><?php $renderRow('new', []); ?></template>
<button type="button" id="add-item" class="secondary" aria-label="Adicionar apontamento" title="Adicionar apontamento">+</button><p id="items-message" class="help" aria-live="polite"></p>
