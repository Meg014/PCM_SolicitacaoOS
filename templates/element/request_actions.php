<?php
// Lucide SVGs: icons/LICENSE. CakePHP mantém rotas, autorização e CSRF.
$admin = $this->getRequest()->getAttribute('identity')->get('role') === \App\Domain\Enum\UserRole::ADMIN_PCM->value;
$showView = $showView ?? true;
$showEdit = $admin && ($showEdit ?? true);
$showDelete = $admin && ($showDelete ?? true);
$viewHref = $viewHref ?? ['controller' => 'Requests', 'action' => 'view', $request->id];
$editHref = $editHref ?? ['controller' => 'Requests', 'action' => 'edit', $request->id];
$dialogId = 'delete-request-' . $request->id . '-' . bin2hex(random_bytes(4));
?>
<div class="request-actions" role="group" aria-label="Ações da solicitação <?= h($request->number) ?>">
<?php if ($showView): ?>
<?= $this->Html->link($this->element('icons/eye') . '<span>Ver</span>', $viewHref, ['class' => 'request-action request-action-view', 'title' => 'Ver solicitação', 'escape' => false]) ?>
<?php endif; ?>
<?php if ($showEdit): ?>
<?= $this->Html->link($this->element('icons/pencil') . '<span>Editar</span>', $editHref, ['class' => 'request-action request-action-edit', 'title' => 'Editar solicitação', 'escape' => false]) ?>
<?php endif; ?>
<?php if ($showDelete): ?>
<button type="button" class="request-action request-action-delete" title="Excluir solicitação" data-delete-dialog="<?= h($dialogId) ?>" aria-haspopup="dialog" aria-controls="<?= h($dialogId) ?>"><?= $this->element('icons/trash-2') ?><span>Excluir</span></button>
<dialog id="<?= h($dialogId) ?>" class="request-delete-dialog" aria-labelledby="<?= h($dialogId) ?>-title" aria-describedby="<?= h($dialogId) ?>-description">
<h2 id="<?= h($dialogId) ?>-title">Excluir solicitação?</h2>
<p id="<?= h($dialogId) ?>-description">Você está prestes a excluir a solicitação <?= h($request->number) ?>. Esta ação removerá a solicitação da listagem e dos relatórios.</p>
<p class="help">O histórico será preservado. A O.S. no TOTVS não será alterada.</p>
<?= $this->Form->create(null, ['type' => 'post', 'url' => ['controller' => 'Requests', 'action' => 'delete', $request->id, '_method' => 'POST']]) ?>
<?= $this->Form->control('reason', ['id' => $dialogId . '-reason', 'label' => 'Motivo da exclusão (opcional)', 'type' => 'textarea', 'rows' => 2, 'maxlength' => 2000, 'placeholder' => 'Limpeza administrativa de registros de teste']) ?>
<div class="request-dialog-actions">
<button type="button" class="request-action request-action-view" data-delete-cancel autofocus>Cancelar</button>
<?= $this->Form->button('Confirmar exclusão', ['class' => 'request-action request-action-delete']) ?>
</div>
<?= $this->Form->end() ?>
</dialog>
<?php endif; ?>
</div>
