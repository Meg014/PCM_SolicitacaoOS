<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class ServiceRequest extends Entity
{
    public function attendanceDuration(?\DateTimeInterface $now = null): string
    {
        $end = match ($this->status) {
            \App\Domain\Enum\RequestStatus::AGUARDANDO_PCM->value,
            \App\Domain\Enum\RequestStatus::EM_ANALISE->value => $now ?? \Cake\I18n\DateTime::now('UTC'),
            \App\Domain\Enum\RequestStatus::OS_ABERTA->value,
            \App\Domain\Enum\RequestStatus::EM_EXECUCAO->value,
            \App\Domain\Enum\RequestStatus::AGUARDANDO_ENCERRAMENTO->value,
            \App\Domain\Enum\RequestStatus::ENCERRADA->value,
            \App\Domain\Enum\RequestStatus::ATENDIDA->value => $this->os_linked_at,
            \App\Domain\Enum\RequestStatus::CANCELADA->value => $this->cancelled_at,
            default => null,
        };
        if (!$this->created_at instanceof \DateTimeInterface || !$end instanceof \DateTimeInterface) {
            return 'Não informado';
        }
        $minutes = intdiv(max(0, $end->getTimestamp() - $this->created_at->getTimestamp()), 60);
        if ($minutes < 60) { return $minutes . ' min'; }
        $hours = intdiv($minutes, 60);
        if ($hours < 24) { return $hours . 'h' . ($minutes % 60 ? ' ' . ($minutes % 60) . 'min' : ''); }

        return intdiv($hours, 24) . 'd' . ($hours % 24 ? ' ' . ($hours % 24) . 'h' : '');
    }

    protected array $_accessible = [
        'sector_id' => true, 'asset_code' => true, 'asset_branch' => true,
        'maintenance_type' => true, 'description' => true, 'unit' => true, 'flow' => true, 'os_disposition' => true,
    ];
}
