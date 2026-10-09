<?php $this->assign('title', 'Relatório de solicitações'); ?>
<h1>Relatório de solicitações</h1>
<p>Consulte os pedidos por período, situação, mão de obra, solicitante ou equipamento.</p>
<?php if ($error): ?><p class="error-message" role="alert"><?= h($error) ?></p><?php endif; ?>
<?= $this->Form->create(null, ['type' => 'get', 'class' => 'dashboard-filters']) ?>
<?php $value = fn($key) => is_string($filters[$key] ?? null) ? $filters[$key] : ''; ?>
<?= $this->Form->control('start', ['label' => 'Data inicial', 'type' => 'date', 'value' => $value('start')]) ?>
<?= $this->Form->control('end', ['label' => 'Data final', 'type' => 'date', 'value' => $value('end')]) ?>
<?= $this->Form->control('status', ['label' => 'Situação', 'options' => $statuses, 'empty' => 'Todas', 'value' => $value('status')]) ?>
<?= $this->Form->control('flow', ['label' => 'Tipo de solicitação', 'options' => \App\Domain\Enum\RequestFlow::options(), 'empty' => 'Todos', 'value' => $value('flow')]) ?>
<?= $this->Form->control('sector_id', ['label' => 'Mão de obra', 'options' => $sectors, 'empty' => 'Todos', 'value' => $value('sector_id')]) ?>
<?= $this->Form->control('unit', ['label' => 'Unidade', 'options' => \App\Domain\Enum\RequestUnit::options() + ['UNSPECIFIED' => 'Não informada (anteriores)'], 'empty' => 'Todas', 'value' => $value('unit')]) ?>
<?= $this->Form->control('user_id', ['label' => 'Solicitante', 'options' => $users, 'empty' => 'Todos', 'value' => $value('user_id')]) ?>
<?= $this->Form->control('asset', ['label' => 'Bem / equipamento', 'maxlength' => 100, 'value' => $value('asset')]) ?>
<?= $this->Form->control('q', ['label' => 'Buscar número ou descrição', 'maxlength' => 100, 'value' => $value('q')]) ?>
<?= $this->Form->button('Filtrar') ?>
<?= $this->Html->link('Limpar filtros', '/relatorios') ?>
<?= $this->Form->end() ?>
<?php if (!count($requests)): ?><p>Nenhuma solicitação encontrada. Revise os filtros ou escolha outro período.</p><?php else: ?>
<div class="table-scroll" role="region" aria-label="Relatório de solicitações" tabindex="0">
<table class="report-table"><thead><tr><th>Solicitação</th><th>Data e hora</th><th>Tempo de atendimento</th><th>Solicitante</th><th>Unidade</th><th>Mão de obra</th><th>Equipamento</th><th>Situação</th><th>O.S. TOTVS</th></tr></thead><tbody>
<?php foreach ($requests as $request): ?>
<tr><td><?= $this->Html->link($request->number, ['controller' => 'Requests', 'action' => 'view', $request->id]) ?></td><td><?= h($request->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></td><td><?= h($request->attendanceDuration()) ?></td><td><?= h($request->user->name) ?></td><td><?= h(\App\Domain\Enum\RequestUnit::display($request->unit)) ?></td><td><?= h($request->sector->name) ?></td><td><?= h($request->asset_description) ?> (<?= h($request->asset_code) ?>)</td><td><?= h($statuses[$request->status]) ?></td><td><?= h($request->totvs_os_number ?: 'Ainda não vinculada') ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?= $this->element('pagination') ?>
<?php endif; ?>
