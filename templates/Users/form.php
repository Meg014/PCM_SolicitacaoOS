<?php $this->assign('title', $user->isNew() ? 'Cadastrar usuário' : 'Editar usuário'); ?>
<section class="panel">
<h1><?= h($this->fetch('title')) ?></h1>
<?= $this->Form->create($user) ?>
<?= $this->Form->control('name', ['label' => 'Nome', 'maxlength' => 150, 'autocomplete' => 'name']) ?>
<?= $this->Form->control('username', ['label' => 'Usuário / matrícula (opcional)', 'required' => false, 'maxlength' => 60, 'autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false']) ?>
<p class="help">Informe usuário/matrícula ou e-mail; pelo menos um é necessário. Para usuário, use letras sem acento, números, ponto, hífen ou sublinhado.</p>
<?= $this->Form->control('email', ['label' => 'E-mail (opcional)', 'type' => 'email', 'required' => false, 'maxlength' => 254, 'autocomplete' => 'email']) ?>
<?= $this->Form->control('password', ['label' => $user->isNew() ? 'Senha provisória' : 'Nova senha provisória (deixe em branco para manter)', 'type' => 'password', 'value' => '', 'required' => $user->isNew(), 'minlength' => 6, 'autocomplete' => 'new-password']) ?>
<p class="help">Senha inicial / redefinida: use pelo menos 6 caracteres. O usuário deverá criar sua senha pessoal no próximo acesso.</p>
<?= $this->Form->control('role', ['label' => 'Perfil', 'options' => $roles, 'empty' => 'Selecione o perfil']) ?>
<?= $this->Form->control('active', ['label' => 'Usuário ativo', 'type' => 'checkbox', 'default' => true]) ?>
<?= $this->Form->button('Salvar usuário') ?>
<?= $this->Form->end() ?>
<?= $this->Html->link('Voltar aos usuários', ['action' => 'index']) ?>
</section>
