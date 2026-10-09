<?php $this->assign('title', 'Indicadores de solicitações'); ?>
<h1>Indicadores de solicitações</h1>
<?= $this->Form->create(null, ['type' => 'get', 'class' => 'dashboard-filters']) ?>
<?php $value = fn($key) => is_string($filters[$key] ?? null) ? $filters[$key] : ''; ?>
<?= $this->Form->control('start', ['label' => 'Data inicial', 'type' => 'date', 'value' => $value('start')]) ?>
<?= $this->Form->control('end', ['label' => 'Data final', 'type' => 'date', 'value' => $value('end')]) ?>
<?= $this->Form->button('Filtrar período') ?>
<?= $this->Html->link('Limpar filtros', ['controller' => 'Reports', 'action' => 'indicators']) ?>
<?= $this->Form->end() ?>
<?php if ($error): ?><p class="error-message" role="alert"><?= h($error) ?></p><?php elseif ($indicators): ?>
<p>Total de solicitações: <strong><?= $indicators['total'] ?></strong></p>
<p>Tempo médio até o vínculo da O.S.: <strong><?= $indicators['average_seconds'] === null ? 'Ainda não há O.S. vinculadas no período' : number_format((float)$indicators['average_seconds'] / 3600, 1, ',', '.') . ' horas' ?></strong></p>
<p class="help">O período considera a data de criação dos pedidos. A média considera somente pedidos com data de vínculo.</p>
<?php if (!$indicators['total']): ?><p>Nenhuma solicitação no período. Escolha outro período ou registre o primeiro pedido.</p><?php endif; ?>
<div class="indicator-groups">
<?php foreach (['status' => 'Por situação', 'sector' => 'Por mão de obra', 'user' => 'Por solicitante', 'type' => 'Por tipo de manutenção'] as $key => $title): ?>
<section><h2><?= h($title) ?></h2>
<table><thead><tr><th>Grupo</th><th>Solicitações</th></tr></thead><tbody>
<?php foreach ($indicators[$key] as $row): ?>
<?php $label = match ($key) { 'status' => $statuses[$row['category']] ?? '', 'sector' => $sectors[$row['category']] ?? '', 'user' => $users[$row['category']] ?? '', 'type' => \App\Domain\Enum\MaintenanceType::from($row['category'])->label() }; ?>
<tr><td><?= h($label) ?></td><td><?= (int)$row['total'] ?></td></tr>
<?php endforeach; ?>
</tbody></table></section>
<?php endforeach; ?>
</div>
<h2>Equipamentos mais solicitados</h2>
<table><thead><tr><th>Equipamento</th><th>Solicitações</th></tr></thead><tbody>
<?php foreach ($indicators['equipment'] as $row): ?><tr><td><?= h($row['name']) ?> (<?= h($row['code']) ?>)</td><td><?= (int)$row['total'] ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
