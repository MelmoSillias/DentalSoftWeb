<?php

namespace App\Tests\Patient;

use App\Billing\Infrastructure\Persistence\Doctrine\FactureRepository;
use App\CareDelivery\Domain\Entity\Consultation;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\ConsultationRepository;
use App\Patient\Application\Mapper\PatientSummaryMapper;
use App\Patient\Application\ReadModel\PatientSummaryProvider;
use App\Patient\Domain\Entity\Patient;
use PHPUnit\Framework\TestCase;

final class PatientSummaryProviderTest extends TestCase
{
    private function patient(int $id): Patient
    {
        $patient = (new Patient())->setNom('Doe')->setPrenom('Jane');
        (new \ReflectionProperty(Patient::class, 'id'))->setValue($patient, $id);

        return $patient;
    }

    public function testCollectionBatchesBalancesPreservesKeysAndDefaultsMissingBalancesToZero(): void
    {
        $first = $this->patient(7);
        $second = $this->patient(8);
        $closed = (new Consultation())->setStatut(1)->setNoteSeance('Closed');
        $invoices = $this->createMock(FactureRepository::class);
        $invoices->expects(self::once())->method('sumUnpaidResteByPatientIds')
            ->with([4 => 7, 9 => 8])->willReturn([7 => 30]);
        $consultations = $this->createMock(ConsultationRepository::class);
        $consultations->expects(self::exactly(2))->method('findLatestClosedByPatient')
            ->willReturnMap([[$first, $closed], [$second, null]]);
        $mapper = new PatientSummaryMapper();
        $provider = new PatientSummaryProvider($consultations, $invoices, $mapper);

        $result = $provider->getSummaries([4 => $first, 9 => $second]);

        self::assertSame([
            4 => $mapper->formatPatientSummary($first, $closed, 30),
            9 => $mapper->formatPatientSummary($second, null, 0),
        ], $result);
    }

    public function testEmptyCollectionDoesNotQueryBalancesOrConsultations(): void
    {
        $invoices = $this->createMock(FactureRepository::class);
        $invoices->expects(self::never())->method('sumUnpaidResteByPatientIds');
        $consultations = $this->createMock(ConsultationRepository::class);
        $consultations->expects(self::never())->method('findLatestClosedByPatient');

        self::assertSame([], (new PatientSummaryProvider($consultations, $invoices, new PatientSummaryMapper()))->getSummaries([]));
    }

    public function testSingleSummaryLoadsItsBalanceAndSelectedConsultationBeforeMapping(): void
    {
        $patient = $this->patient(7);
        $closed = (new Consultation())->setStatut(1)->setNoteSeance('Latest');
        $consultations = $this->createMock(ConsultationRepository::class);
        $consultations->expects(self::once())->method('findLatestClosedByPatient')->with($patient)->willReturn($closed);
        $invoices = $this->createMock(FactureRepository::class);
        $invoices->expects(self::once())->method('sumUnpaidResteByPatientIds')->with([7])->willReturn([7 => 80]);
        $mapper = new PatientSummaryMapper();
        $provider = new PatientSummaryProvider($consultations, $invoices, $mapper);

        self::assertSame($mapper->formatPatientSummary($patient, $closed, 80), $provider->getSummary($patient));
    }

    public function testMissingSingleBalanceDefaultsToZero(): void
    {
        $consultations = $this->createMock(ConsultationRepository::class);
        $invoices = $this->createMock(FactureRepository::class);
        $invoices->expects(self::once())->method('sumUnpaidResteByPatientIds')->with([7])->willReturn([]);
        $provider = new PatientSummaryProvider($consultations, $invoices, new PatientSummaryMapper());

        self::assertSame(0, $provider->getSummary($this->patient(7))['impayees']);
    }

    public function testLatestClosedConsultationTakesPrecedenceOverEntityFallback(): void
    {
        $latest = (new Consultation())->setStatut(1);
        $patient = $this->patient(7)->setDerniereConsultation((new Consultation())->setStatut(1));
        $consultations = $this->createMock(ConsultationRepository::class);
        $consultations->expects(self::once())->method('findLatestClosedByPatient')->with($patient)->willReturn($latest);
        $invoices = $this->createMock(FactureRepository::class);
        $invoices->expects(self::never())->method('sumUnpaidResteByPatientIds');
        $provider = new PatientSummaryProvider($consultations, $invoices, new PatientSummaryMapper());

        self::assertSame($latest, $provider->resolveLatestConsultation($patient));
    }

    public static function fallbackCases(): iterable
    {
        yield 'closed' => [1, true];
        yield 'active' => [0, false];
        yield 'other-status' => [2, false];
        yield 'missing' => [null, false];
    }

    /** @dataProvider fallbackCases */
    public function testFallbackConsultationMustBeClosed(?int $status, bool $expected): void
    {
        $fallback = $status === null ? null : (new Consultation())->setStatut($status);
        $patient = $this->patient(7)->setDerniereConsultation($fallback);
        $consultations = $this->createMock(ConsultationRepository::class);
        $consultations->method('findLatestClosedByPatient')->willReturn(null);
        $provider = new PatientSummaryProvider($consultations, $this->createMock(FactureRepository::class), new PatientSummaryMapper());

        self::assertSame($expected ? $fallback : null, $provider->resolveLatestConsultation($patient));
    }
}
