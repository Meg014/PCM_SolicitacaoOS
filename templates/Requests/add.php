<?php $this->assign('hideRequestShortcut', '1');
$this->assign('title', 'Nova Ordem de Serviço');
$this->Html->script('request-form', ['block' => true, 'defer' => true]);
$data = $this->getRequest()->getData();
$text = fn($value) => is_scalar($value) ? (string)$value : '';


?>
<section class="request-form">
<h1>Nova Ordem de Serviço</h1>
<p>Solicitante: <strong><?= h($this->getRequest()->getAttribute('identity')->get('name')) ?></strong></p>
<?php /* Unidade é independente da filial TOTVS do Bem. */ ?>
<noscript><p>Ative o JavaScript do navegador para pesquisar e selecionar equipamento e mão de obra.</p></noscript>
<?= $this->Form->create($request, ['id' => 'request-form', 'type' => 'file']) ?>

<?= $this->Form->control('unit', ['label' => 'Unidade', 'options' => \App\Domain\Enum\RequestUnit::options(), 'empty' => 'Selecione a unidade', 'required' => true]) ?>
<div class="autocomplete" data-lookup="<?= h($this->Url->build(['controller' => 'Lookups', 'action' => 'assets'])) ?>" data-kind="asset">
    <?= $this->Form->control('asset_label', ['label' => 'Bem / Equipamento', 'value' => $text($data['asset_label'] ?? ''), 'required' => true, 'autocomplete' => 'off', 'role' => 'combobox', 'aria-autocomplete' => 'list', 'aria-expanded' => 'false', 'aria-controls' => 'asset-results', 'aria-describedby' => 'asset-help', 'data-search' => true]) ?>
    <?= $this->Form->hidden('asset_code', ['value' => $text($data['asset_code'] ?? ''), 'data-selected' => true]) ?>
    <?= $this->Form->hidden('asset_branch', ['value' => $text($data['asset_branch'] ?? ''), 'data-branch' => true]) ?>
    <?= $this->Form->error('asset_code') ?>
    <p id="asset-help" class="help">Digite pelo menos 2 caracteres do código ou nome e selecione o equipamento.</p>
    <div id="asset-results" class="lookup-results" role="listbox" aria-label="Equipamentos encontrados" hidden></div>
    <p class="help lookup-message" aria-live="polite"></p>
</div>
<section id="active-alert" class="active-alert" data-url="<?= h($this->Url->build(['controller' => 'Lookups', 'action' => 'active'])) ?>" aria-labelledby="active-title" hidden>
<h2 id="active-title">Já existe uma Ordem de Serviço ativa para este equipamento</h2>
<p>Confira se é o mesmo serviço. Você pode continuar uma execução existente ou confirmar um novo pedido para outro problema.</p>
<div id="active-requests"></div>
<?= $this->Form->control('confirm_existing', ['type' => 'checkbox', 'label' => 'Conferi as solicitações existentes e quero criar um novo pedido', 'id' => 'confirm-existing']) ?>
<?= $this->Form->control('duplicate_reason', ['label' => 'Motivo do novo pedido (opcional)', 'type' => 'textarea', 'maxlength' => 2000]) ?>
</section>
<?= $this->Form->error('confirm_existing') ?>
<div class="autocomplete" data-lookup="<?= h($this->Url->build(['controller' => 'Lookups', 'action' => 'sectors'])) ?>" data-kind="sector">
    <?= $this->Form->control('sector_label', ['label' => 'Mão de obra', 'value' => $text($data['sector_label'] ?? ''), 'required' => true, 'autocomplete' => 'off', 'role' => 'combobox', 'aria-autocomplete' => 'list', 'aria-expanded' => 'false', 'aria-controls' => 'sector-results', 'aria-describedby' => 'sector-help', 'data-search' => true]) ?>
    <?= $this->Form->hidden('sector_id', ['value' => $text($data['sector_id'] ?? ''), 'data-selected' => true]) ?>
    <?= $this->Form->error('sector_id') ?>
    <p id="sector-help" class="help">Pesquise pelo nome ou código e selecione uma mão de obra ativa.</p>
    <div id="sector-results" class="lookup-results" role="listbox" aria-label="Mãos de obra encontradas" hidden></div>
    <p class="help lookup-message" aria-live="polite"></p>
</div>
<?= $this->Form->control('maintenance_type', ['label' => 'Tipo de manutenção', 'options' => $types, 'empty' => 'Selecione o tipo', 'required' => true]) ?>
<?= $this->Form->control('description', ['label' => 'Descrição do serviço / problema', 'type' => 'textarea', 'rows' => 4, 'maxlength' => 5000, 'required' => true]) ?>
<fieldset id="os-disposition"><legend>Como esta Ordem de Serviço deve seguir após a abertura?</legend>
<div class="os-options">
<?php foreach (\App\Domain\Enum\OsDisposition::cases() as $choice): ?>
<label class="os-option"><input type="radio" name="os_disposition" value="<?= h($choice->value) ?>" required <?= ($data['os_disposition'] ?? '') === $choice->value ? 'checked' : '' ?>>
<span><strong><?= h($choice->label()) ?></strong><small><?= h($choice->explanation()) ?></small></span></label>
<?php endforeach; ?>
</div><?= $this->Form->error('os_disposition') ?></fieldset>
<fieldset id="execution-initial">
    <legend>Apontamentos</legend>
    <p>Se já houve execução, registre o serviço. Os apontamentos iniciais são opcionais; você poderá continuar após o vínculo da O.S.</p>
    <?= $this->Form->error('items') ?>
    <?= $this->element('execution_items') ?>
</fieldset>
<?= $this->Form->control('photos[]', ['type' => 'file', 'label' => 'Fotos (opcional)', 'multiple' => true, 'accept' => 'image/jpeg,image/png,image/webp', 'id' => 'photos', 'aria-describedby' => 'photos-help']) ?>
<p id="photos-help" class="help" aria-live="polite">Até 5 fotos JPG, PNG ou WebP, com até 5 MB cada. No celular, você pode escolher fotos ou usar a câmera. Se ocorrer um erro, selecione as fotos novamente.</p>
<?= $this->Form->button('Enviar solicitação') ?>
<?= $this->Form->end() ?>
</section>
