<?php

namespace App\Tests\Unit\Patient\Application\Command\SoftDeletePatient;

use App\Patient\Application\Command\SoftDeletePatient\SoftDeletePatientCommand;
use App\Patient\Application\Command\SoftDeletePatient\SoftDeletePatientHandler;
use App\Patient\Application\Port\CloseActiveConsultationsPort;
use App\Patient\Application\Port\PatientCachePort;
use App\Patient\Application\Port\PatientRealtimePort;
use App\Patient\Domain\Model\Patient;
use App\Patient\Domain\Repository\PatientRepository;
use App\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\TransactionManager;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SoftDeletePatientHandlerTest extends TestCase
{
    public function testSoftDeleteClosesConsultationsViaPortThenMarksDeleted(): void
    {
        $patient = Patient::create([
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'sexe' => 'F',
            'telephone' => '770000000',
        ], new DateTimeImmutable('2026-01-01 10:00:00'));
        $patient->assignId(PatientId::fromInt(15));

        $repository = $this->createMock(PatientRepository::class);
        $repository->expects(self::exactly(2))
            ->method('findActiveById')
            ->with(self::callback(static fn (PatientId $id): bool => $id->toInt() === 15))
            ->willReturn($patient);
        $repository->expects(self::once())->method('save')->with($patient);

        $closePort = $this->createMock(CloseActiveConsultationsPort::class);
        $closePort->expects(self::once())
            ->method('closeActiveConsultations')
            ->with(15, 9);

        $cache = $this->createMock(PatientCachePort::class);
        $cache->expects(self::once())->method('clearPatientsCache');

        $realtime = $this->createMock(PatientRealtimePort::class);
        $realtime->expects(self::once())->method('publishPatientRefresh')->with(15, 'deleted');

        $clock = $this->createMock(Clock::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-08-10 12:00:00'));

        $transactions = $this->createMock(TransactionManager::class);
        $transactions->method('transactional')->willReturnCallback(
            static fn (callable $callback): mixed => $callback()
        );

        $handler = new SoftDeletePatientHandler(
            $repository,
            $closePort,
            $cache,
            $realtime,
            $clock,
            $transactions,
        );

        $result = $handler(new SoftDeletePatientCommand(15, 9));

        self::assertTrue($result['success']);
        self::assertSame('Patient déplacé dans la corbeille.', $result['message']);
        self::assertTrue($patient->isDeleted());
    }

    public function testMissingPatientReturns404(): void
    {
        $repository = $this->createMock(PatientRepository::class);
        $repository->method('findActiveById')->willReturn(null);

        $transactions = $this->createMock(TransactionManager::class);
        $transactions->method('transactional')->willReturnCallback(
            static fn (callable $callback): mixed => $callback()
        );

        $handler = new SoftDeletePatientHandler(
            $repository,
            $this->createMock(CloseActiveConsultationsPort::class),
            $this->createMock(PatientCachePort::class),
            $this->createMock(PatientRealtimePort::class),
            $this->createMock(Clock::class),
            $transactions,
        );

        $result = $handler(new SoftDeletePatientCommand(404, null));

        self::assertSame(404, $result['status']);
        self::assertSame('Patient non trouvé', $result['error']);
    }
}
