<?php $this->assign('title', $request->number); ?>
<h1><?= h($request->number) ?></h1>
<?php if ($request->flow === \App\Domain\Enum\RequestFlow::ACOMPANHAMENTO->value && in_array($request->status, [\App\Domain\Enum\RequestStatus::OS_ABERTA->value, \App\Domain\Enum\RequestStatus::EM_EXECUCAO->value], true)): ?>
<div class="actions execution-shortcuts">
<?= $this->Html->link('Adicionar apontamento / continuar serviço', '#execution', ['class' => 'button']) ?>
</div>
<?php endif; ?>
<p class="status" data-status="<?= h($request->status) ?>"><?= h(\App\Domain\Enum\RequestStatus::from($request->status)->label()) ?></p>
<?= $this->element('os_disposition', ['request' => $request]) ?>
<dl class="request-details">
    <dt>Tipo de solicitação</dt><dd><?= h(\App\Domain\Enum\RequestFlow::from($request->flow)->label()) ?></dd>
    <dt>Unidade</dt><dd><?= h(\App\Domain\Enum\RequestUnit::display($request->unit)) ?></dd>
    <dt>Solicitante</dt><dd><?= h($request->user->name) ?></dd>
    <dt>Data e hora</dt><dd><?= h($request->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></dd>
    <dt>Tempo de atendimento</dt><dd><?= h($request->attendanceDuration()) ?></dd>
    <dt>Equipamento</dt><dd><?= h($request->asset_description) ?> (<?= h($request->asset_code) ?>)</dd>
    <dt>Mão de obra</dt><dd><?= h($request->sector->name) ?></dd>
    <dt>Tipo de manutenção</dt><dd><?= h(\App\Domain\Enum\MaintenanceType::from($request->maintenance_type)->label()) ?></dd>
    <?php if ($request->totvs_os_number): ?><dt>O.S. TOTVS</dt><dd><?= h($request->totvs_os_number) ?></dd><?php endif; ?>
    <?php if ($request->assigned_at): ?><dt>Assumida por</dt><dd><?= h($request->assigned_user->name) ?> · <?= h($request->assigned_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></dd><?php endif; ?>
    <?php if ($request->os_linked_at): ?><dt>O.S. vinculada por</dt><dd><?= h($request->linked_user->name) ?> · <?= h($request->os_linked_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></dd><?php endif; ?>
    <?php if ($request->cancelled_at): ?><dt>Cancelada por</dt><dd><?= h($request->cancelled_user->name) ?> · <?= h($request->cancelled_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></dd><dt>Motivo</dt><dd class="preserve-lines"><?= h($request->cancellation_reason) ?></dd><?php endif; ?>
</dl>
<?php
$isPcm = $this->getRequest()->getAttribute('identity')->get('role') === \App\Domain\Enum\UserRole::ADMIN_PCM->value;
$pending = in_array($request->status, [\App\Domain\Enum\RequestStatus::AGUARDANDO_PCM->value, \App\Domain\Enum\RequestStatus::EM_ANALISE->value], true);
?>
<?php if ($isPcm && $pending): ?>
<section class="handling" aria-labelledby="handling-title">
<h2 id="handling-title">Ações do PCM</h2>
<?= $this->element('os_disposition', ['request' => $request]) ?>
<p class="help"><?= $request->status === \App\Domain\Enum\RequestStatus::AGUARDANDO_PCM->value ? 'Aguardando análise: assuma o pedido ou registre o resultado do atendimento.' : 'Em análise: vincule a Ordem de Serviço criada ou cancele com motivo.' ?></p>
<?php if ($request->status === \App\Domain\Enum\RequestStatus::AGUARDANDO_PCM->value): ?>
<h3>Assumir atendimento</h3>
<?= $this->Form->create(null, ['url' => ['action' => 'assign', $request->id, '_method' => 'POST']]) ?>
<?= $this->Form->button('Assumir solicitação') ?>
<?= $this->Form->end() ?>
<?php endif; ?>
<h3>Vincular Ordem de Serviço</h3>
<?= $this->Form->create(null, ['url' => ['action' => 'link', $request->id, '_method' => 'POST']]) ?>
<?= $this->Form->control('totvs_os_number', ['label' => 'Número da O.S. TOTVS', 'required' => true, 'maxlength' => 100]) ?>
<p class="help">Confira o número da O.S. que você abriu no TOTVS antes de confirmar.</p>
<?= $this->Form->button('Vincular O.S. TOTVS') ?>
<?= $this->Form->end() ?>
<details><summary>Cancelar ou recusar solicitação</summary>
<?= $this->Form->create(null, ['url' => ['action' => 'cancel', $request->id, '_method' => 'POST']]) ?>
<?= $this->Form->control('reason', ['label' => 'Motivo do cancelamento / recusa', 'type' => 'textarea', 'required' => true, 'maxlength' => 2000]) ?>
<?= $this->Form->button('Cancelar solicitação', ['class' => 'secondary']) ?>
<?= $this->Form->end() ?>
</details>
</section>
<?php endif; ?>
<?php if ($isPcm && $request->status === \App\Domain\Enum\RequestStatus::AGUARDANDO_ENCERRAMENTO->value): ?>
<section class="handling"><h2>Ações do PCM</h2>
<?= $this->element('os_disposition', ['request' => $request]) ?>
<p>O serviço foi concluído. Encerre a O.S. <?= h($request->totvs_os_number) ?> no TOTVS e depois confirme aqui.</p>
<?= $this->Form->create(null, ['url' => ['action' => 'close', $request->id, '_method' => 'POST']]) ?>
<?= $this->Form->control('confirmed_closed', ['type' => 'checkbox', 'label' => 'Confirmo que já encerrei esta Ordem de Serviço no TOTVS', 'required' => true]) ?>
<?= $this->Form->button('Confirmar encerramento no TOTVS') ?><?= $this->Form->end() ?>
</section>
<?php endif; ?>
<?php if ($request->status === \App\Domain\Enum\RequestStatus::ATENDIDA->value): ?><p class="message">Pedido de abertura atendido. Isso não significa que a Ordem de Serviço foi encerrada no TOTVS.</p><?php endif; ?>
<h2>Descrição do serviço / problema</h2>
<?php if ($isPcm && !$pending && in_array($request->status, \App\Domain\Enum\RequestStatus::activeValues(), true)): ?>
<details class="handling"><summary>Ações do PCM: cancelar ou recusar</summary>
<?= $this->element('os_disposition', ['request' => $request]) ?>
<p>Esta ação cancela o acompanhamento local. A Ordem de Serviço no TOTVS deve ser tratada manualmente.</p>
<?= $this->Form->create(null, ['url' => ['action' => 'cancel', $request->id, '_method' => 'POST']]) ?>
<?= $this->Form->control('reason', ['label' => 'Motivo do cancelamento / recusa', 'type' => 'textarea', 'required' => true, 'maxlength' => 2000]) ?>
<?= $this->Form->button('Cancelar solicitação', ['class' => 'secondary']) ?><?= $this->Form->end() ?>
</details>
<?php endif; ?>
<p class="preserve-lines"><?= h($request->description) ?></p>
<h2>Apontamentos</h2>
<?php if (!$request->request_items): ?><p>Nenhum apontamento registrado.</p><?php endif; ?>
<ol>
<?php foreach ($request->request_items as $item): ?>
<li class="item-detail">
<p><strong>Executante:</strong> <?= h($item->executor ?: 'Não informado (registro anterior)') ?></p>
<p><strong>Data e horário:</strong> <?= $item->started_at && $item->ended_at ? h($item->started_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') . ' até ' . $item->ended_at->setTimezone('America/Sao_Paulo')->format('H:i')) : 'Não informados (registro anterior)' ?></p>
<p class="preserve-lines"><?= h($item->description) ?></p>
</li>
<?php endforeach; ?>
</ol>
<?php if ($request->flow === \App\Domain\Enum\RequestFlow::ACOMPANHAMENTO->value && in_array($request->status, [\App\Domain\Enum\RequestStatus::OS_ABERTA->value, \App\Domain\Enum\RequestStatus::EM_EXECUCAO->value], true)): ?>
<section class="handling" id="execution"><h2>Continuar apontando nesta O.S.</h2>
<p>Qualquer integrante autorizado pode registrar o serviço realizado em outro dia ou turno. Informe quem executou e o período de cada apontamento.</p>
<?= $this->Form->create(null, ['url' => ['action' => 'items', $request->id, '_method' => 'POST']]) ?>
<?= $this->element('execution_items') ?>
<?= $this->Form->button('Registrar apontamentos') ?><?= $this->Form->end() ?>
<?= $this->Form->create(null, ['url' => ['action' => 'ongoing', $request->id, '_method' => 'POST']]) ?>
<?= $this->Form->button('Serviço ainda em andamento', ['class' => 'secondary']) ?><?= $this->Form->end() ?>
<?= $this->Form->create(null, ['url' => ['action' => 'complete', $request->id, '_method' => 'POST']]) ?>
<p class="help">Ao solicitar o encerramento, os apontamentos ficam concluídos e o PCM recebe a pendência.</p>
<?= $this->Form->button('Serviço concluído - solicitar encerramento') ?><?= $this->Form->end() ?>
</section>
<?php endif; ?>
<h2>Histórico</h2>
<ol class="timeline">
<?php foreach ($request->request_audits as $audit): ?>
    <?php
    $event = match ($audit->action) {
        'REQUEST_CREATED' => 'Solicitação enviada.', 'REQUEST_ASSIGNED' => 'Solicitação assumida.',
        'TOTVS_OS_LINKED' => 'O.S. TOTVS vinculada: ' . ($audit->new_values['totvs_os_number'] ?? ''),
        'REQUEST_CANCELLED' => 'Solicitação cancelada: ' . ($audit->new_values['cancellation_reason'] ?? ''),
        'ATTACHMENT_ADDED' => 'Foto anexada: ' . ($audit->new_values['name'] ?? ''),
        'EXECUTION_ITEMS_ADDED' => 'Apontamentos de execução registrados.',
        'SERVICE_ONGOING' => 'Serviço continua em andamento.',
        'SERVICE_COMPLETED' => 'Serviço concluído. Encerramento solicitado ao PCM.',
        'TOTVS_OS_CLOSED' => 'PCM confirmou o encerramento da Ordem de Serviço no TOTVS.',
        'DUPLICATE_CONFIRMED' => 'Novo pedido confirmado apesar de solicitações ativas para o equipamento. ' . ($audit->new_values['reason'] ?? ''),
        'OS_DISPOSITION_CHANGED' => 'Orientação atualizada: ' . \App\Domain\Enum\OsDisposition::from($audit->new_values['os_disposition'])->heading() . '.',
        'STATUS_CHANGED' => 'Situação alterada para ' . \App\Domain\Enum\RequestStatus::from($audit->new_values['status'])->label() . '.',
        default => 'Solicitação atualizada.',
    };
    ?>
    <li><?= h($audit->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?> — <?= h($event) ?> · <?= h($audit->user?->name ?? 'Sistema') ?>
    <?php if ($audit->action === 'REQUEST_CREATED' && !empty($audit->new_values['os_disposition'])): ?>
    <p><?= h(\App\Domain\Enum\OsDisposition::from($audit->new_values['os_disposition'])->heading()) ?></p>
    <?php endif; ?>
    <?php if (!empty($audit->new_values['items'])): ?>
    <ul class="item-history">
    <?php foreach ($audit->new_values['items'] as $item): ?>
    <li><?= h($item['executor'] ?? 'Executante não informado (registro anterior)') ?>
        <?php if (!empty($item['started_at']) && !empty($item['ended_at'])): ?> · <?= h((new \Cake\I18n\DateTime($item['started_at']))->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?> até <?= h((new \Cake\I18n\DateTime($item['ended_at']))->setTimezone('America/Sao_Paulo')->format('H:i')) ?><?php endif; ?>
        <p class="preserve-lines"><?= h($item['description'] ?? '') ?></p>
    </li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if ($isPcm): ?><details><summary>Ver registro de auditoria</summary><pre><?= h(json_encode(['antes' => $audit->old_values, 'depois' => $audit->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details><?php endif; ?>
    </li>
<?php endforeach; ?>
</ol>
<h2>Fotos</h2>
<?php if (!$request->request_attachments): ?><p>Nenhuma foto anexada a esta solicitação.</p><?php endif; ?>
<div class="attachment-list">
<?php foreach ($request->request_attachments as $photo): ?>
<figure>
<?= $this->Html->link($this->Html->image(['controller' => 'Attachments', 'action' => 'view', $photo->id], ['alt' => $photo->original_name, 'loading' => 'lazy']), ['controller' => 'Attachments', 'action' => 'view', $photo->id], ['escape' => false]) ?>
<figcaption><?= h($photo->original_name) ?> · <?= number_format($photo->size / 1024, 0, ',', '.') ?> KB</figcaption>
</figure>
<?php endforeach; ?>
</div>
<?= $this->Html->link('Voltar às minhas solicitações', ['action' => 'index'], ['class' => 'button secondary']) ?>
