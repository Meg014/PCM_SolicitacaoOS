<?php $this->assign('title', 'Minhas solicitações'); ?>
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
</article>
<?php endforeach; ?>
</div>
<?= $this->element('pagination') ?>
