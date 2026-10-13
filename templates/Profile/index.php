<?php $this->assign('title', 'Meu perfil'); ?>
<section class="panel"><h1>Meu perfil</h1>
<dl class="request-details"><dt>Nome</dt><dd><?= h($user->name) ?></dd><dt>Usuário</dt><dd><?= h($user->username ?: 'Não informado') ?></dd><dt>E-mail</dt><dd><?= h($user->email ?: 'Não informado') ?></dd><dt>Setor</dt><dd><?= h($user->user_department?->name ?: 'Não informado (registro anterior)') ?></dd></dl>
<?= $this->Html->link('Alterar minha senha', ['controller' => 'Profile', 'action' => 'password'], ['class' => 'button']) ?>
</section>
