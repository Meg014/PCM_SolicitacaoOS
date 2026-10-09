<?php $disposition = \App\Domain\Enum\OsDisposition::tryFrom((string)$request->os_disposition); ?>
<?php if ($disposition): ?>
<div class="os-disposition" data-disposition="<?= h($disposition->value) ?>">
<strong><?= h($disposition->heading()) ?></strong>
<?php if (!($compact ?? false)): ?><p><?= h($disposition->explanation()) ?></p><?php endif; ?>
</div>
<?php else: ?>
<p class="help">Orientação de abertura/encerramento não informada (registro anterior).</p>
<?php endif; ?>
