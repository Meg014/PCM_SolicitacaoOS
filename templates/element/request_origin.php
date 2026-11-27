<?php if ($request->checklist_route !== null): ?>
<div>Origem: Checklist</div>
<div>Rota: <?= h($request->checklist_route) ?></div>
<?php else: ?>
<div>Origem: Solicitação manual</div>
<?php endif; ?>
