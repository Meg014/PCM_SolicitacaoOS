<?php
$this->assign('title', 'Fazer apontamento nesta O.S.');
$this->Html->script('request-form', ['block' => true, 'defer' => true]);
?>
<section class="panel">
<h1>Fazer apontamento nesta O.S.</h1>
<p><strong><?= h($request->number) ?></strong> · O.S. <?= h($request->totvs_os_number) ?></p>
<p><?= h($request->asset_description) ?> (<?= h($request->asset_code) ?>)</p>
<p><?= h($request->description) ?></p>
<p>O apontamento será incluído nesta solicitação, mantendo o solicitante e todos os registros anteriores.</p>
<?= $this->Form->create(null) ?>
<?= $this->element('execution_items') ?>
<?= $this->Form->button('Salvar apontamento') ?>
<?= $this->Form->end() ?>
<?= $this->Html->link('Ver solicitação', ['action' => 'view', $request->id], ['class' => 'button secondary']) ?>
</section>
