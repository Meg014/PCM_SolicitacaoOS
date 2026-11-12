<?php if ($this->getRequest()->getAttribute('identity')->get('role') === \App\Domain\Enum\UserRole::ADMIN_PCM->value): ?>
<?= $this->Html->link('✏ Editar', ['controller' => 'Requests', 'action' => 'edit', $request->id], ['class' => 'request-action', 'title' => 'Editar solicitação', 'aria-label' => 'Editar solicitação']) ?>
<?php if (trim((string)$request->totvs_os_number) === ''): ?>
<?= $this->Form->postLink('🗑 Excluir', ['controller' => 'Requests', 'action' => 'delete', '_method' => 'POST', $request->id], ['class' => 'request-action request-action-delete', 'title' => 'Excluir solicitação', 'aria-label' => 'Excluir solicitação', 'confirm' => 'Tem certeza que deseja excluir definitivamente esta solicitação? Esta ação não poderá ser desfeita.']) ?>
<?php else: ?><button class="request-action request-action-delete" type="button" disabled title="Solicitação com O.S. TOTVS vinculada não pode ser excluída.">🗑 Excluir</button><?php endif; ?>
<?php endif; ?>
