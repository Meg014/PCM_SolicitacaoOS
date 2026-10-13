<?php $this->assign('title', 'Detalhes da Ordem de Serviço no TOTVS'); ?>
<h1>Detalhes da Ordem de Serviço no TOTVS</h1>
<p><?= h($request->number) ?> · O.S. <?= h($request->totvs_os_number) ?></p>
<p class="help">Consulta em tempo real. Alterações e encerramento são realizados exclusivamente no TOTVS.</p>
<?php if ($error): ?><p class="message" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($order): ?>
<dl class="request-details">
<?php
$labels = ['number' => 'Número da O.S.', 'branch' => 'Filial', 'situation' => 'Situação (código TOTVS)', 'finished' => 'Término (código TOTVS)',
    'asset_code' => 'Código do Bem', 'asset_name' => 'Bem/equipamento', 'description' => 'Descrição', 'service_code' => 'Serviço',
    'service_name' => 'Nome do serviço', 'cost_center' => 'Centro de custo', 'area' => 'Área', 'maintenance_code' => 'Tipo (código TOTVS)',
    'origin_date' => 'Data de origem', 'planned_start' => 'Início previsto', 'planned_start_time' => 'Hora inicial prevista',
    'planned_end' => 'Fim previsto', 'planned_end_time' => 'Hora final prevista', 'actual_start' => 'Início real', 'actual_start_time' => 'Hora inicial real',
    'actual_end' => 'Fim real', 'actual_end_time' => 'Hora final real'];
$date = function ($value) {
    if (!is_string($value) || !preg_match('/^\d{8}$/D', $value)) { return $value ?: 'Não informado'; }
    $parsed = \DateTimeImmutable::createFromFormat('!Ymd', $value);
    return $parsed && $parsed->format('Ymd') === $value ? $parsed->format('d/m/Y') : 'Não informado';
};
?>
<dt>Encerramento</dt><dd><?= match (\App\Service\TotvsOrderState::resolve($order)) { 'OPEN' => 'Aberta / em andamento', 'CLOSED' => 'Encerrada no TOTVS', default => 'Situação ainda não confirmada' } ?></dd>
<?php foreach ($labels as $key => $label): ?><dt><?= h($label) ?></dt><dd class="preserve-lines"><?= h(in_array($key, ['origin_date', 'planned_start', 'planned_end', 'actual_start', 'actual_end']) ? $date($order[$key] ?? null) : ($order[$key] ?? 'Não informado')) ?></dd><?php endforeach; ?>
</dl>
<?php endif; ?>
<?= $this->Html->link('Voltar aos relatórios', ['action' => 'index'], ['class' => 'button secondary']) ?>
