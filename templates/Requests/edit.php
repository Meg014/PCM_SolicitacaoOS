<?php
$this->assign('title', 'Editar solicitação');
$this->Html->script('request-form.js', ['block' => true, 'defer' => true]);
$this->Html->script('service-selection.js', ['block' => true, 'defer' => true]);
$data = $this->getRequest()->is('post') ? $this->getRequest()->getData() : $request->toArray() + ['asset_label' => $request->asset_description . ' (' . $request->asset_code . ')'];
$text = fn($key) => is_scalar($data[$key] ?? null) ? (string)$data[$key] : '';
?>
<section class="request-form">
<h1>Editar solicitação <?= h($request->number) ?></h1>
<p>As alterações serão registradas na auditoria. Apontamentos e anexos serão preservados.</p>
<?= $this->Form->create($request, ['id' => 'request-form', 'type' => 'post', 'url' => ['controller' => 'Requests', 'action' => 'edit', $request->id, '_method' => 'POST']]) ?>
<?= $this->Form->control('unit', ['label' => 'Unidade', 'options' => \App\Domain\Enum\RequestUnit::options(), 'empty' => 'Selecione', 'required' => true]) ?>
<div class="autocomplete" data-lookup="<?= h($this->Url->build(['controller' => 'Lookups', 'action' => 'assets'])) ?>" data-kind="asset">
<?= $this->Form->control('asset_label', ['label' => 'Bem / Equipamento', 'value' => $text('asset_label'), 'required' => true, 'autocomplete' => 'off', 'role' => 'combobox', 'aria-autocomplete' => 'list', 'aria-expanded' => 'false', 'aria-controls' => 'asset-results', 'data-search' => true]) ?>
<?= $this->Form->hidden('asset_code', ['value' => $text('asset_code'), 'data-selected' => true]) ?>
<?= $this->Form->hidden('asset_branch', ['value' => $text('asset_branch'), 'data-branch' => true]) ?>
<?= $this->Form->error('asset_code') ?>
<div id="asset-results" class="lookup-results" role="listbox" aria-label="Equipamentos encontrados" hidden></div>
<p class="help lookup-message" aria-live="polite"></p>
</div>
<?= $this->Form->control('sector_id', ['label' => 'Mão de obra', 'options' => $sectors, 'empty' => 'Selecione', 'required' => true]) ?>
<?= $this->Form->control('maintenance_type', ['label' => 'Tipo de manutenção', 'options' => $types, 'empty' => 'Selecione', 'required' => true]) ?>
<?= $this->element('service_pair', ['pairData' => $data]) ?>
<?= $this->Form->control('os_disposition', ['id' => 'admin-os-disposition', 'label' => 'Fluxo da O.S.', 'options' => array_column(array_map(fn($choice) => ['value' => $choice->value, 'label' => $choice->label()], \App\Domain\Enum\OsDisposition::cases()), 'label', 'value'), 'empty' => 'Selecione', 'required' => true]) ?>
<p class="help">O fluxo só pode mudar enquanto aguarda PCM, antes de vincular a O.S. Solicitações com apontamentos devem manter acompanhamento. Equipamentos com O.S. vinculada não podem ser trocados.</p>
<?= $this->Form->button('Salvar alterações') ?>
<?= $this->Html->link('Voltar à solicitação', ['action' => 'view', $request->id], ['class' => 'button secondary']) ?>
<?= $this->Form->end() ?>
</section>
