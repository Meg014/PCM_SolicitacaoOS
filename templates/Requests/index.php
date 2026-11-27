<?php $this->assign('hideRequestShortcut', '1'); $this->assign('title', 'Minhas solicitações'); ?>
<div class="page-heading">
    <h1>Minhas solicitações</h1>
    <?= $this->Html->link('+ Solicitar Ordem de Serviço', ['action' => 'add'], ['class' => 'button request-shortcut']) ?>
</div>
<dl class="status-counts">
<?php foreach ($ownCounts as $status => $count): ?><div><dt><?= h(\App\Domain\Enum\RequestStatus::from($status)->label()) ?></dt><dd><?= $count ?></dd></div><?php endforeach; ?>
</dl>
<?= $this->Form->create(null, ['type' => 'get']) ?>
<?= $this->Form->control('status', ['label' => 'Filtrar por situação', 'options' => $statuses, 'empty' => 'Todas', 'value' => is_string($this->getRequest()->getQuery('status')) ? $this->getRequest()->getQuery('status') : '']) ?>
<?= $this->Form->button('Filtrar', ['class' => 'secondary']) ?>
<?= $this->Form->end() ?>
<?php if (count($requests) === 0): ?>
<p>Nenhuma solicitação encontrada. Use “Nova Ordem de Serviço” para registrar seu pedido.</p>
<?php endif; ?>
<?php if ($maintainer): ?>
<p class="table-scroll-help">Deslize a tabela para os lados para consultar todas as colunas.</p>
<div class="table-scroll" tabindex="0" role="region" aria-label="Minhas solicitações">
<table class="report-table"><thead><tr>
<?php foreach (['Solicitação', 'Data/Hora', 'Bem / Equipamento', 'Mão de obra', 'Tipo', 'Situação', 'O.S. TOTVS', 'Tempo de atendimento', 'Ações'] as $heading): ?><th scope="col"><?= h($heading) ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php foreach ($requests as $request): ?>
<tr>
<td><?= $this->Html->link($request->number, ['action' => 'view', $request->id]) ?></td>
<td><?= h($request->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></td>
<td><?= h($request->asset_description) ?></td>
<td><?= h($request->sector->name) ?></td>
<td><?= h(\App\Service\MaintenanceTypeCatalog::label($request->maintenance_type)) ?></td>
<td><span class="status" data-status="<?= h($request->status) ?>"><?= h($statuses[$request->status]) ?></span></td>
<td><?php if ($request->totvs_os_number): ?><strong class="status" data-status="OS_ABERTA">O.S. <?= h($request->totvs_os_number) ?></strong><?php else: ?>—<?php endif; ?></td>
<td><?= h($request->attendanceDuration()) ?></td>
<td><div class="request-actions" role="group" aria-label="Ações da solicitação <?= h($request->number) ?>">
<?= $this->Html->link('👁 Ver', ['action' => 'view', $request->id], ['class' => 'request-action request-action-view', 'title' => 'Ver solicitação']) ?>
<?php if (\App\Service\RequestAccess::canEdit($request, \App\Domain\Enum\UserRole::MANTENEDOR->value, (int)$this->getRequest()->getAttribute('identity')->getIdentifier())): ?>
<?= $this->Html->link('✏ Editar', ['action' => 'edit', $request->id], ['class' => 'request-action request-action-edit', 'title' => 'Editar solicitação']) ?>
<?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<div class="request-list">
<?php foreach ($requests as $request): ?>
<article>
    <h2><?= $this->Html->link($request->number, ['action' => 'view', $request->id]) ?></h2>
    <p class="status" data-status="<?= h($request->status) ?>"><?= h(\App\Domain\Enum\RequestStatus::from($request->status)->label()) ?></p>
    <p><?= h($request->asset_description) ?> · <?= h($request->sector->name) ?></p>
    <p class="help"><?= h(\App\Domain\Enum\RequestFlow::from($request->flow)->label()) ?></p>
    <p class="help">Unidade: <?= h(\App\Domain\Enum\RequestUnit::display($request->unit)) ?></p>
    <p class="help"><?= h($request->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></p>
    <p class="help">Tempo de atendimento: <?= h($request->attendanceDuration()) ?></p>
    <?php if ($request->totvs_os_number): ?><p>O.S. TOTVS: <?= h($request->totvs_os_number) ?></p><?php endif; ?>
    <?= $this->element('request_actions', ['request' => $request]) ?>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?= $this->element('pagination') ?>
