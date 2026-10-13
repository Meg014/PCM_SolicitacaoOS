<?php $this->assign('title', 'Usuários'); ?>
<h1>Usuários</h1>
<?= $this->Html->link('Cadastrar usuário', ['action' => 'add'], ['class' => 'button']) ?>
<p>Para remover o acesso de uma pessoa, edite seu cadastro e desmarque “Usuário ativo”.</p>
<div class="table-scroll">
<table>
    <thead><tr><th>Nome</th><th>Usuário</th><th>E-mail</th><th>Setor</th><th>Perfil</th><th>Situação</th><th>Ação</th></tr></thead>
    <tbody>
    <?php $labels = \App\Domain\Enum\UserRole::options(); ?>
    <?php foreach ($users as $user): ?>
        <tr><td><?= h($user->name) ?></td><td><?= h($user->username ?: 'Não informado') ?></td><td><?= h($user->email ?: 'Não informado') ?></td><td><?= h($user->user_department?->name ?: 'Não informado (registro anterior)') ?></td><td><?= h($labels[$user->role] ?? '') ?></td><td><?= $user->active ? 'Ativo' : 'Inativo' ?></td><td><?= $this->Html->link('Editar usuário', ['action' => 'edit', $user->id]) ?></td></tr>
    <?php endforeach; ?>
    <?php if (count($users) === 0): ?><tr><td colspan="7">Nenhum usuário cadastrado.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?= $this->element('pagination') ?>
