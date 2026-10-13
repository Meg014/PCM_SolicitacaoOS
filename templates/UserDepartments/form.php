<?php $this->assign('title', $department->isNew() ? 'Cadastrar setor' : 'Editar setor'); ?>
<section class="panel"><h1><?= h($this->fetch('title')) ?></h1>
<p>Setor do funcionário. Este cadastro é separado da Mão de obra das solicitações.</p>
<?= $this->Form->create($department) ?>
<?= $this->Form->control('name', ['label' => 'Nome do setor', 'required' => true, 'maxlength' => 150]) ?>
<?= $this->Form->control('active', ['label' => 'Setor ativo', 'type' => 'checkbox', 'default' => true]) ?>
<?= $this->Form->button('Salvar setor') ?>
<?= $this->Form->end() ?>
<?= $this->Html->link('Voltar aos setores', ['action' => 'index']) ?>
</section>
