<?php $this->assign('title', $catalogTitle); ?>
<h1><?= h($catalogTitle) ?></h1>
<?= $this->Html->link('Cadastrar ' . mb_strtolower($catalogLabel), ['action' => 'add'], ['class' => 'button']) ?>
<p>Serviços inativos permanecem no histórico e não aparecem em novos lançamentos.</p>
<div class="table-scroll">
<table>
    <thead><tr><th><?= h($catalogLabel) ?></th><th>Situação</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach ($services as $service): ?>
        <tr>
            <td><?= h($service->name) ?></td>
            <td><?= $service->active ? 'Ativo' : 'Inativo' ?></td>
            <td>
                <?= $this->Html->link('Editar', ['action' => 'edit', $service->id]) ?>
                <?= $this->Form->postLink($service->active ? 'Desativar' : 'Ativar', $this->Url->build($catalogPath . '/' . $service->id . '/situacao'), [
                    'confirm' => $service->active ? 'Desativar este serviço para novos lançamentos?' : 'Ativar este serviço para novos lançamentos?',
                ]) ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (count($services) === 0): ?><tr><td colspan="3">Nenhum serviço cadastrado.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?= $this->element('pagination') ?>
