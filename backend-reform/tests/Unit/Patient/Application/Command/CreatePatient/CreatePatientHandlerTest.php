<?php

namespace App\Tests\Unit\Patient\Application\Command\CreatePatient;

use App\Patient\Application\Command\CreatePatient\CreatePatientCommand;
use App\Patient\Application\Command\CreatePatient\CreatePatientHandler;
use App\Patient\Application\Port\PatientCachePort;
use App\Patient\Application\Port\PatientCreatedSideEffectsPort;
use App\Patient\Application\Port\PatientInsurancePort;
use App\Patient\Application\Port\PatientRealtimePort;
use App\Patient\Domain\Model\Patient;
use App\Patient\Domain\Repository\PatientRepository;
use App\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\TransactionManager;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CreatePatientHandlerTest extends TestCase
{
    public function testCreatePatientReturnsSuccessShape(): void
    {
        $repository = $this->createMock(PatientRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Patient $patient): void {
                $patient->assignId(PatientId::fromInt(42));
            });

        $insurance = $this->createMock(PatientInsurancePort::class);
        $insurance->expects(self::once())
            ->method('applyInsuranceProfile')
            ->with(42, self::callback(static fn (array $data): bool => ($data['nom'] ?? null) === 'Doe'));

        $sideEffects = $this->createMock(PatientCreatedSideEffectsPort::class);
        $sideEffects->expects(self::once())
            ->method('afterCreate')
            ->with(42, 7)
            ->willReturn([
                'id' => 99,
                'username' => 'doejane',
                'active' => true,
                'roles' => ['ROLE_PATIENT'],
                'defaultPassword' => '123',
            ]);

        $cache = $this->createMock(PatientCachePort::class);
        $cache->expects(self::once())->method('clearPatientsCache');

        $realtime = $this->createMock(PatientRealtimePort::class);
        $realtime->expects(self::once())->method('publishPatientRefresh')->with(42, 'created');

        $clock = $this->createMock(Clock::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-08-07 10:00:00'));

        $transactions = $this->createMock(TransactionManager::class);
        $transactions->method('transactional')->willReturnCallback(
            static fn (callable $callback): mixed => $callback()
        );

        $handler = new CreatePatientHandler(
            $repository,
            $insurance,
            $sideEffects,
            $cache,
            $realtime,
            $clock,
            $transactions,
        );

        $result = $handler(new CreatePatientCommand([
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'sexe' => 'F',
            'telephone' => '770000000',
        ], 7));

        self::assertTrue($result['success']);
        self::assertSame(201, $result['status']);
        self::assertSame(42, $result['patientId']);
        self::assertSame('doejane', $result['portalAccount']['username']);
    }

    public function testMissingRequiredFieldsReturns400(): void
    {
        $handler = new CreatePatientHandler(
            $this->createMock(PatientRepository::class),
            $this->createMock(PatientInsurancePort::class),
            $this->createMock(PatientCreatedSideEffectsPort::class),
            $this->createMock(PatientCachePort::class),
            $this->createMock(PatientRealtimePort::class),
            $this->createMock(Clock::class),
            $this->createMock(TransactionManager::class),
        );

        $result = $handler(new CreatePatientCommand(['nom' => 'Doe']));

        self::assertSame(400, $result['status']);
        self::assertSame('Paramètres obligatoires manquants', $result['error']);
    }
}
