<?php

namespace App\Tests\Unit\Patient\Domain\Model;

use App\Patient\Domain\Exception\PatientAlreadyDeletedException;
use App\Patient\Domain\Model\Allergy;
use App\Patient\Domain\Model\Patient;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PatientTest extends TestCase
{
    public function testCreateBuildsActivePatient(): void
    {
        $now = new DateTimeImmutable('2026-08-07 10:00:00');
        $patient = Patient::create([
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'sexe' => 'F',
            'telephone' => '+221770000000',
            'email' => 'jane@example.com',
        ], $now);

        self::assertNull($patient->getId());
        self::assertSame('Doe', $patient->getNom());
        self::assertSame('Jane', $patient->getPrenom());
        self::assertSame('jane@example.com', $patient->getEmail()?->toString());
        self::assertFalse($patient->isDeleted());
        self::assertStringStartsWith('PAT-', $patient->getNumCarnet());
        self::assertSame($now, $patient->getDateInscription());
    }

    public function testSoftDeleteAndRestore(): void
    {
        $patient = Patient::create([
            'nom' => 'Doe',
            'prenom' => 'John',
            'sexe' => 'M',
            'telephone' => '770000001',
        ], new DateTimeImmutable());

        $deletedAt = new DateTimeImmutable('2026-08-07 12:00:00');
        $patient->softDelete($deletedAt);

        self::assertTrue($patient->isDeleted());
        self::assertSame($deletedAt, $patient->getDeletedAt());
        self::assertNull($patient->getLastConsultationId());

        $patient->restore();

        self::assertFalse($patient->isDeleted());
        self::assertNull($patient->getDeletedAt());
    }

    public function testAddAllergy(): void
    {
        $patient = Patient::create([
            'nom' => 'Doe',
            'prenom' => 'Ada',
            'sexe' => 'F',
            'telephone' => '770000002',
        ], new DateTimeImmutable());

        $patient->addAllergy(Allergy::create('Pénicilline', 'Réaction cutanée'));

        self::assertCount(1, $patient->getAllergies());
        self::assertSame('Pénicilline', $patient->getAllergies()[0]->getLibelle());
    }

    public function testUpdateRejectedWhenDeleted(): void
    {
        $patient = Patient::create([
            'nom' => 'Doe',
            'prenom' => 'Deleted',
            'sexe' => 'M',
            'telephone' => '770000003',
        ], new DateTimeImmutable());
        $patient->softDelete(new DateTimeImmutable());

        $this->expectException(PatientAlreadyDeletedException::class);
        $patient->update(['nom' => 'Other']);
    }
}
