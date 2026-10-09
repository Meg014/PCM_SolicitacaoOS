<?php $this->assign('title', 'Fila de solicitações de Ordem de Serviço'); ?>
<div class="page-heading">
    <h1>Fila de solicitações de Ordem de Serviço</h1>
    <?= $this->Html->link('+ Solicitar Ordem de Serviço', ['controller' => 'Requests', 'action' => 'add'], ['class' => 'button request-shortcut']) ?>
</div>
<dl class="status-counts">
<?php
$shortLabels = [
    \App\Domain\Enum\RequestStatus::AGUARDANDO_PCM->value => 'Aguardando PCM',
    \App\Domain\Enum\RequestStatus::EM_ANALISE->value => 'Em análise',
    \App\Domain\Enum\RequestStatus::OS_ABERTA->value => 'O.S. vinculadas',
    \App\Domain\Enum\RequestStatus::CANCELADA->value => 'Canceladas',
];
?>
<?php foreach ($counts as $status => $count): ?><div><dt><?= h(\App\Domain\Enum\RequestStatus::from($status)->label()) ?></dt><dd><?= $count ?></dd></div><?php endforeach; ?>
</dl>
<p class="help">Solicitações ativas primeiro, priorizando o encerramento pelo PCM. Em cada situação, as mais recentes aparecem no topo. Contadores consideram os demais filtros.</p>
<?php foreach ($errors as $error): ?><p class="error-message" role="alert"><?= h($error) ?></p><?php endforeach; ?>
<details class="filter-panel" open><summary>Filtrar solicitações</summary>
<?= $this->Form->create(null, ['type' => 'get', 'class' => 'dashboard-filters']) ?>
<?= $this->Form->control('status', ['label' => 'Situação', 'options' => $statuses, 'empty' => 'Todas', 'value' => $filters['status']]) ?>
<?= $this->Form->control('flow', ['label' => 'Tipo de solicitação', 'options' => \App\Domain\Enum\RequestFlow::options(), 'empty' => 'Todos', 'value' => $filters['flow']]) ?>
<?= $this->Form->control('sector_id', ['label' => 'Mão de obra', 'options' => $sectors, 'empty' => 'Todos', 'value' => $filters['sector_id']]) ?>
<?= $this->Form->control('unit', ['label' => 'Unidade', 'options' => \App\Domain\Enum\RequestUnit::options() + ['UNSPECIFIED' => 'Não informada (anteriores)'], 'empty' => 'Todas', 'value' => $filters['unit']]) ?>
<?= $this->Form->control('start', ['label' => 'Data inicial', 'type' => 'date', 'value' => $filters['start']]) ?>
<?= $this->Form->control('end', ['label' => 'Data final', 'type' => 'date', 'value' => $filters['end']]) ?>
<?= $this->Form->button('Filtrar') ?>
<?= $this->Html->link('Limpar filtros', ['controller' => 'Dashboard', 'action' => 'index']) ?>
<?= $this->Form->end() ?>
</details>
<?php if (!count($requests)): ?><p class="message">Nenhuma solicitação encontrada para os filtros escolhidos.</p><?php else: ?>
<p id="queue-scroll-help" class="table-scroll-help help">Deslize a tabela para os lados para ver todas as informações e ações.</p>
<div class="table-scroll" tabindex="0" role="region" aria-label="Fila de solicitações de Ordem de Serviço" aria-describedby="queue-scroll-help">
<table class="queue-table">
<thead><tr><th>Solicitação</th><th>Tempo de atendimento</th><th>Solicitante</th><th>Mão de obra</th><th>Equipamento</th><th>Tipo</th><th>Situação</th><th>Ação</th></tr></thead>
<tbody>
<?php foreach ($requests as $request): ?>
<tr>
<td><?= $this->Html->link($request->number, ['controller' => 'Requests', 'action' => 'view', $request->id]) ?><br><span class="help"><?= h($request->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?><br><?= h(\App\Domain\Enum\RequestFlow::from($request->flow)->label()) ?></span></td>
<td><?= h($request->attendanceDuration()) ?></td>
<td><?= h($request->user->name) ?></td><td><?= h($request->sector->name) ?><br><span class="help">Unidade: <?= h(\App\Domain\Enum\RequestUnit::display($request->unit)) ?></span></td>
<td><?= h($request->asset_description) ?><br><span class="help"><?= h($request->asset_code) ?></span></td>
<td><?= h(\App\Domain\Enum\MaintenanceType::from($request->maintenance_type)->label()) ?></td>
<td><span class="status" data-status="<?= h($request->status) ?>"><?= h($statuses[$request->status]) ?></span><?php if ($request->totvs_os_number): ?><br><span class="help">O.S. <?= h($request->totvs_os_number) ?></span><?php endif; ?><?= $this->element('os_disposition', ['request' => $request, 'compact' => true]) ?></td>
<td><?= $this->Html->link('Ver solicitação', ['controller' => 'Requests', 'action' => 'view', $request->id], ['class' => 'button secondary']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?= $this->element('pagination') ?>
<?php endif; ?>
