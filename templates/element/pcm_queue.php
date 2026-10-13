<dl class="status-counts">
<?php foreach ($counts as $status => $count): ?><div><dt><?= h($statuses[$status]) ?></dt><dd><?= $count ?></dd></div><?php endforeach; ?>
</dl>
<?php if (!count($requests)): ?><p class="message">Nenhuma solicitação pendente agora.</p><?php else: ?>
<p id="queue-scroll-help" class="table-scroll-help help">Deslize a tabela para os lados para ver todas as informações e ações.</p>
<div class="table-scroll" tabindex="0" role="region" aria-label="Fila de solicitações de Ordem de Serviço" aria-describedby="queue-scroll-help">
<table class="queue-table"><thead><tr><th>Solicitação</th><th>Tempo de atendimento</th><th>Solicitante</th><th>Mão de obra</th><th>Equipamento</th><th>Tipo</th><th>Situação</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($requests as $request): ?>
<tr>
<td><?= $this->Html->link($request->number, ['controller' => 'Requests', 'action' => 'view', $request->id]) ?><br><span class="help"><?= h($request->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></span><?= $this->element('request_activity', ['request' => $request]) ?></td>
<td><?= h($request->attendanceDuration()) ?></td><td><?= h($request->user->name) ?></td>
<td><?= h($request->sector->name) ?><br><span class="help">Unidade: <?= h(\App\Domain\Enum\RequestUnit::display($request->unit)) ?></span></td>
<td><?= h($request->asset_description) ?><br><span class="help"><?= h($request->asset_code) ?></span></td>
<td><?= h(\App\Domain\Enum\MaintenanceType::from($request->maintenance_type)->label()) ?></td>
<td><span class="status" data-status="<?= h($request->status) ?>"><?= h($statuses[$request->status]) ?></span><?php if ($request->totvs_os_number): ?><br><span class="help">O.S. <?= h($request->totvs_os_number) ?></span><?php endif; ?><?= $this->element('os_disposition', ['request' => $request, 'compact' => true]) ?></td>
<td><?= $this->Html->link('Ver solicitação', ['controller' => 'Requests', 'action' => 'view', $request->id], ['class' => 'button secondary']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php $this->Paginator->options(['url' => ['controller' => 'Dashboard', 'action' => 'index']]); ?>
<?= $this->element('pagination') ?>
<?php endif; ?>
