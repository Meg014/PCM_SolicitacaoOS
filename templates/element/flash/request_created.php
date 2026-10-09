<section class="message success request-confirmation" role="status" aria-labelledby="confirmation-title">
<h2 id="confirmation-title">Solicitação enviada ao PCM</h2>
<p>Seu pedido <strong><?= h($params['number']) ?></strong> foi registrado com sucesso.</p>
<div class="actions">
<?= $this->Html->link('Ver solicitação', ['controller' => 'Requests', 'action' => 'view', $params['id']], ['class' => 'button']) ?>
<?= $this->Html->link('Criar outra solicitação', ['controller' => 'Requests', 'action' => 'add'], ['class' => 'button secondary']) ?>
</div>
</section>
