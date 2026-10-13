<?php
$this->assign('hideRequestShortcut', '1');
$this->assign('title', 'Pendências do PCM');
$this->Html->script('pcm-queue', ['block' => true, 'defer' => true]);
?>
<div class="page-heading">
<h1>Pendências do PCM</h1>
<?= $this->Html->link('+ Solicitar Ordem de Serviço', ['controller' => 'Requests', 'action' => 'add'], ['class' => 'button request-shortcut']) ?>
</div>
<p class="help">Encerramentos e novos pedidos primeiro. A fila de execução considera a atividade mais recente.</p>
<p id="queue-update-message" class="help" role="status" aria-live="polite">A fila é atualizada automaticamente a cada 30 segundos.</p>
<div id="pcm-queue" tabindex="-1" data-refresh-url="<?= h($this->Url->build(['controller' => 'Dashboard', 'action' => 'refresh'])) ?>">
<?= $this->element('pcm_queue') ?>
</div>
