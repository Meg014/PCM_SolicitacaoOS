<?php if ($request->last_item_at): ?>
<p class="new-activity"><strong>NOVO APONTAMENTO</strong><br>Em <?= h($request->last_item_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></p>
<?php endif; ?>
