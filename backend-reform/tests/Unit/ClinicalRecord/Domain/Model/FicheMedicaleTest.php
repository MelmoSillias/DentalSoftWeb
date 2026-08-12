<?php

namespace App\Tests\Unit\ClinicalRecord\Domain\Model;

use App\ClinicalRecord\Domain\Exception\ClinicalRecordDomainException;
use App\ClinicalRecord\Domain\Model\FicheMedicale;
use App\ClinicalRecord\Domain\ValueObject\FicheMedicaleId;
use PHPUnit\Framework\TestCase;

final class FicheMedicaleTest extends TestCase
{
    public function testReconstituteExposesPatientAndArchiveState(): void
    {
        $fiche = FicheMedicale::reconstitute(FicheMedicaleId::fromInt(10), 5);

        self::assertSame(10, $fiche->requireId()->toInt());
        self::assertSame(5, $fiche->getPatientId());
        self::assertFalse($fiche->isArchived());
    }

    public function testArchiveMarksFicheAsArchived(): void
    {
        $fiche = FicheMedicale::reconstitute(FicheMedicaleId::fromInt(1), 2);

        $fiche->archive();

        self::assertTrue($fiche->isArchived());
    }

    public function testArchiveRejectedWhenAlreadyArchived(): void
    {
        $fiche = FicheMedicale::reconstitute(FicheMedicaleId::fromInt(1), 2, true);

        $this->expectException(ClinicalRecordDomainException::class);
        $this->expectExceptionMessage('Fiche medicale is already archived.');

        $fiche->archive();
    }

    public function testRejectsInvalidPatientId(): void
    {
        $this->expectException(ClinicalRecordDomainException::class);
        $this->expectExceptionMessage('FicheMedicale requires a valid patientId.');

        FicheMedicale::reconstitute(FicheMedicaleId::fromInt(1), 0);
    }
}
