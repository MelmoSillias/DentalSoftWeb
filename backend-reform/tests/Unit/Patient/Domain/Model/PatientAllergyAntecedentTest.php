<?php

namespace App\Tests\Unit\Patient\Domain\Model;

use App\Patient\Domain\Exception\PatientAlreadyDeletedException;
use App\Patient\Domain\Exception\PatientDomainException;
use App\Patient\Domain\Model\Allergy;
use App\Patient\Domain\Model\Antecedent;
use App\Patient\Domain\Model\Patient;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PatientAllergyAntecedentTest extends TestCase
{
    public function testAddAndRemoveAllergy(): void
    {
        $patient = $this->createPatient();
        $allergy = Allergy::create('Pénicilline', 'Réaction cutanée');
        $allergy->assignId(11);

        $patient->addAllergy($allergy);
        self::assertCount(1, $patient->getAllergies());

        $patient->removeAllergy(11);
        self::assertCount(0, $patient->getAllergies());
    }

    public function testRemoveUnknownAllergyThrows(): void
    {
        $patient = $this->createPatient();

        $this->expectException(PatientDomainException::class);
        $this->expectExceptionMessage('Allergy not found on patient.');
        $patient->removeAllergy(999);
    }

    public function testAddAndRemoveAntecedent(): void
    {
        $patient = $this->createPatient();
        $antecedent = Antecedent::create('Diabète', 'médical', new DateTimeImmutable('2026-08-07'));
        $antecedent->assignId(22);

        $patient->addAntecedent($antecedent);
        self::assertCount(1, $patient->getAntecedents());
        self::assertSame('Diabète', $patient->getAntecedents()[0]->getDescription());

        $patient->removeAntecedent(22);
        self::assertCount(0, $patient->getAntecedents());
    }

    public function testRemoveUnknownAntecedentThrows(): void
    {
        $patient = $this->createPatient();

        $this->expectException(PatientDomainException::class);
        $this->expectExceptionMessage('Antecedent not found on patient.');
        $patient->removeAntecedent(999);
    }

    public function testAddAllergyRejectedWhenDeleted(): void
    {
        $patient = $this->createPatient();
        $patient->softDelete(new DateTimeImmutable());

        $this->expectException(PatientAlreadyDeletedException::class);
        $patient->addAllergy(Allergy::create('Latex'));
    }

    public function testAllergyRequiresLibelle(): void
    {
        $this->expectException(PatientDomainException::class);
        Allergy::create('   ');
    }

    private function createPatient(): Patient
    {
        return Patient::create([
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'sexe' => 'F',
            'telephone' => '770000010',
        ], new DateTimeImmutable());
    }
}
