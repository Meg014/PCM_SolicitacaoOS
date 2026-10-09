<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Entity;

use App\Domain\Enum\RequestStatus;
use App\Model\Entity\ServiceRequest;
use PHPUnit\Framework\TestCase;

class ServiceRequestDurationTest extends TestCase
{
    public function testPendingDurationAdvancesInBothStatuses(): void
    {
        foreach ([RequestStatus::AGUARDANDO_PCM, RequestStatus::EM_ANALISE] as $status) {
            $request = new ServiceRequest(['created_at' => new \DateTimeImmutable('2026-10-07 12:00:00 UTC'), 'status' => $status->value]);
            foreach (['12:12:00' => '12 min', '13:11:00' => '1h 11min'] as $time => $expected) {
                $this->assertSame($expected, $request->attendanceDuration(new \DateTimeImmutable('2026-10-07 ' . $time . ' UTC')));
            }
            $this->assertSame('2d 3h', $request->attendanceDuration(new \DateTimeImmutable('2026-10-09 15:00:00 UTC')));
        }
    }

    public function testTerminalDurationIsFrozenAtCorrectEvent(): void
    {
        foreach ([RequestStatus::ATENDIDA, RequestStatus::CANCELADA, RequestStatus::OS_ABERTA, RequestStatus::EM_EXECUCAO, RequestStatus::AGUARDANDO_ENCERRAMENTO, RequestStatus::ENCERRADA] as $status) {
            $request = new ServiceRequest([
                'created_at' => new \DateTimeImmutable('2026-10-07 12:00:00 UTC'), 'status' => $status->value,
                'os_linked_at' => new \DateTimeImmutable('2026-10-07 13:11:00 UTC'),
                'cancelled_at' => new \DateTimeImmutable('2026-10-07 12:12:00 UTC'),
            ]);
            $expected = $status === RequestStatus::CANCELADA ? '12 min' : '1h 11min';
            $this->assertSame($expected, $request->attendanceDuration(new \DateTimeImmutable('2026-10-08 UTC')));
            $this->assertSame($expected, $request->attendanceDuration(new \DateTimeImmutable('2027-10-08 UTC')));
        }
    }

    public function testMissingTerminalTimestampNeverUsesCurrentTime(): void
    {
        foreach ([RequestStatus::ATENDIDA, RequestStatus::CANCELADA] as $status) {
            $request = new ServiceRequest(['created_at' => new \DateTimeImmutable('2026-10-07 UTC'), 'status' => $status->value]);
            $this->assertSame('Não informado', $request->attendanceDuration(new \DateTimeImmutable('2027-10-07 UTC')));
        }
    }

    public function testTimezoneAndNonnegativeDuration(): void
    {
        $request = new ServiceRequest(['created_at' => new \DateTimeImmutable('2026-10-07 09:00:00-03:00'), 'status' => RequestStatus::EM_ANALISE->value]);
        $this->assertSame('12 min', $request->attendanceDuration(new \DateTimeImmutable('2026-10-07 12:12:00 UTC')));
        $this->assertSame('0 min', $request->attendanceDuration(new \DateTimeImmutable('2026-10-07 11:00:00 UTC')));
    }

    public function testClosedTrackingPreservesFrozenOpeningAttendanceDuration(): void
    {
        $request = new ServiceRequest(['created_at' => new \DateTimeImmutable('2026-10-07 12:00 UTC'),
            'status' => RequestStatus::ENCERRADA->value, 'closed_at' => new \DateTimeImmutable('2026-10-09 15:00 UTC'),
            'os_linked_at' => new \DateTimeImmutable('2026-10-07 13:11 UTC')]);
        $this->assertSame('1h 11min', $request->attendanceDuration(new \DateTimeImmutable('2027-10-09 UTC')));
    }
}
