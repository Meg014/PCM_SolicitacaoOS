<?php $this->assign('hideRequestShortcut', '1'); $this->assign('title', 'Acompanhar execução'); ?>
<div class="page-heading"><h1>Acompanhar execução</h1><?= $this->Html->link('+ Solicitar Ordem de Serviço', ['action' => 'add'], ['class' => 'button request-shortcut']) ?></div>
<p>Trabalhos compartilhados entre dias e turnos. Abra a solicitação para continuar apontando ou informar a conclusão do serviço.</p>
<?php if (!count($requests)): ?><p class="message">Nenhuma solicitação em acompanhamento no momento.</p><?php endif; ?>
<?php foreach ($requests as $request): ?>
<article class="request-summary">
<h2><?= $this->Html->link($request->number, ['action' => 'view', $request->id]) ?></h2>
<p><?= h($request->asset_description) ?> (<?= h($request->asset_code) ?>) · <?= h($request->sector->name) ?></p>
<p class="help">Solicitante: <?= h($request->user->name) ?> · <?= h($request->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?> · Tempo de atendimento: <?= h($request->attendanceDuration()) ?></p>
<p><?= h(\App\Domain\Enum\RequestStatus::from($request->status)->label()) ?><?= $request->totvs_os_number ? ' · O.S. ' . h($request->totvs_os_number) : '' ?></p>
<?= $this->element('request_activity', ['request' => $request]) ?><p class="preserve-lines"><?= h($request->description) ?></p>
<?php if (\App\Service\RequestAccess::canAppend($request)): ?><?= $this->Html->link('Fazer apontamento nesta O.S.', ['action' => 'addItem', $request->id], ['class' => 'button']) ?><?php endif; ?><?= $this->Html->link('Ver acompanhamento', ['action' => 'view', $request->id], ['class' => 'button secondary']) ?>
</article>
<?php endforeach; ?>
<?= $this->element('pagination') ?>
