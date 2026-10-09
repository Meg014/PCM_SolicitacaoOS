<?php $this->assign('title', 'Mãos de obra'); ?>
<h1>Mãos de obra</h1>
<?= $this->Html->link('Cadastrar mão de obra', ['action' => 'add'], ['class' => 'button']) ?>
<p>Desative mãos de obra que não devem mais receber solicitações.</p>
<div class="table-scroll">
<table>
    <thead><tr><th>Nome</th><th>Código</th><th>Situação</th><th>Ação</th></tr></thead>
    <tbody>
    <?php foreach ($sectors as $sector): ?>
        <tr><td><?= h($sector->name) ?></td><td><?= h($sector->code ?: '—') ?></td><td><?= $sector->active ? 'Ativo' : 'Inativo' ?></td><td><?= $this->Html->link('Editar mão de obra', ['action' => 'edit', $sector->id]) ?></td></tr>
    <?php endforeach; ?>
    <?php if (count($sectors) === 0): ?><tr><td colspan="4">Nenhuma mão de obra cadastrado. Cadastre o primeira mão de obra para preparar as solicitações.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?= $this->element('pagination') ?>
