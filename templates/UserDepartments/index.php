<?php $this->assign('title', 'Setores dos usuários'); ?>
<h1>Setores dos usuários</h1>
<p>Cadastre os departamentos dos funcionários, como Moenda, Caldeira ou PCM. Eles não são a Mão de obra da solicitação.</p>
<?= $this->Html->link('Cadastrar setor', ['action' => 'add'], ['class' => 'button']) ?>
<div class="table-scroll"><table><thead><tr><th>Setor</th><th>Situação</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($departments as $department): ?>
<tr><td><?= h($department->name) ?></td><td><?= $department->active ? 'Ativo' : 'Inativo' ?></td><td><?= $this->Html->link('Editar setor', ['action' => 'edit', $department->id]) ?></td></tr>
<?php endforeach; ?>
<?php if (!count($departments)): ?><tr><td colspan="3">Nenhum setor cadastrado. Cadastre um setor antes de criar usuários.</td></tr><?php endif; ?>
</tbody></table></div>
<?= $this->element('pagination') ?>
