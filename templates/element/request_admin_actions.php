<?php if ($this->getRequest()->getAttribute('identity')->get('role') === \App\Domain\Enum\UserRole::ADMIN_PCM->value): ?>
<?= $this->Html->link('Editar', ['controller' => 'Requests', 'action' => 'edit', $request->id], ['class' => 'button secondary']) ?>
<?php if (trim((string)$request->totvs_os_number) === ''): ?>
<?= $this->Form->postLink('Excluir', ['controller' => 'Requests', 'action' => 'delete', '_method' => 'POST', $request->id], ['class' => 'button secondary', 'confirm' => 'Tem certeza que deseja excluir definitivamente esta solicitação? Esta ação não poderá ser desfeita.']) ?>
<?php else: ?><button type="button" disabled title="Solicitação com O.S. TOTVS vinculada não pode ser excluída.">Excluir</button><?php endif; ?>
<?php endif; ?>
