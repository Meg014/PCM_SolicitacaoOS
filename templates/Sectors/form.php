<?php $this->assign('title', $sector->isNew() ? 'Cadastrar mão de obra' : 'Editar mão de obra'); ?>
<section class="panel">
<h1><?= h($this->fetch('title')) ?></h1>
<?= $this->Form->create($sector) ?>
<?= $this->Form->control('name', ['label' => 'Nome da mão de obra', 'maxlength' => 150]) ?>
<?= $this->Form->control('code', ['label' => 'Código (opcional)', 'maxlength' => 30]) ?>
<?= $this->Form->control('active', ['label' => 'Mão de obra ativa', 'type' => 'checkbox', 'default' => true]) ?>
<?= $this->Form->button('Salvar mão de obra') ?>
<?= $this->Form->end() ?>
<?= $this->Html->link('Voltar aas mãos de obra', ['action' => 'index']) ?>
</section>
