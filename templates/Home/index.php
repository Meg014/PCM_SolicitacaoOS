<?php $this->assign('title', 'Início'); ?>
<h1>Solicitação de serviços</h1>
<p>Registre um pedido de manutenção ou acompanhe o que você já solicitou.</p>
<div class="actions">
<?= $this->Html->link('Nova Ordem de Serviço', ['controller' => 'Requests', 'action' => 'add'], ['class' => 'button']) ?>
<?= $this->Html->link('Minhas solicitações', ['controller' => 'Requests', 'action' => 'index']) ?>
</div>
