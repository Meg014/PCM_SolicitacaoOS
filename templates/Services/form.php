<?php $this->assign('title', $service->isNew() ? 'Cadastrar ' . mb_strtolower($catalogLabel) : 'Editar ' . mb_strtolower($catalogLabel)); ?>
<section class="panel">
<h1><?= h($this->fetch('title')) ?></h1>
<?= $this->Form->create($service) ?>
<?= $this->Form->control('name', ['label' => 'Nome da opção', 'maxlength' => 150, 'required' => true]) ?>
<?= $this->Form->control('active', ['label' => 'Opção ativa para novos lançamentos', 'type' => 'checkbox', 'default' => true]) ?>
<?= $this->Form->button('Salvar opção') ?>
<?= $this->Form->end() ?>
<?= $this->Html->link('Voltar ao cadastro', ['action' => 'index']) ?>
</section>
