<?php

namespace App\Tests\Patient;

use App\Billing\Domain\Entity\Assurance;
use App\CareDelivery\Domain\Entity\Consultation;
use App\Patient\Application\Mapper\PatientSummaryMapper;
use App\Patient\Domain\Entity\ContactUrgence;
use App\Patient\Domain\Entity\Patient;
use App\Patient\Domain\Entity\PatientAssuranceProfile;
use PHPUnit\Framework\TestCase;

final class PatientSummaryMapperTest extends TestCase
{
    private function patient(int $id): Patient
    {
        $patient = (new Patient())->setNom('Doe')->setPrenom('Jane');
        (new \ReflectionProperty(Patient::class, 'id'))->setValue($patient, $id);

        return $patient;
    }

    public function testSummaryPreservesAllFieldsAndNestedDefaults(): void
    {
        $patient = $this->patient(7)->setPhoto('')->setArchiveFiles([[], ['nom' => 'Scan', 'url' => '/scan.pdf']]);
        $patient->setContactUrgence((new ContactUrgence())->setNom('John')->setTelephone('123')->setLienParente('Frere'));
        $patient->setDateInscription(new \DateTime('2026-01-02 10:30'));
        $patient->setSmsReceipt(true)->setSmsUnsubscribed(true);
        $consultation = (new Consultation())->setStatut(1)->setCreatedAt(new \DateTimeImmutable('2026-03-04 11:12'))->setNoteSeance('Controle');
        (new \ReflectionProperty(Consultation::class, 'id'))->setValue($consultation, 21);
        $mapper = new PatientSummaryMapper();

        self::assertSame([
            'id' => 7, 'nom' => 'Doe', 'prenom' => 'Jane', 'fullname' => 'Doe Jane',
            'photo' => null, 'age' => null, 'dateNaissance' => null, 'sexe' => null,
            'telephone' => null, 'dateInscription' => '2026-01-02 10:30', 'email' => null,
            'adresse' => null, 'profession' => null, 'lieuNaissance' => null, 'groupeSanguin' => null,
            'referencement' => '',
            'contactUrgence' => ['nom' => 'John', 'telephone' => '123', 'lienParente' => 'Frere'],
            'smsPreferences' => [
                'patientCreated' => false, 'receipt' => true, 'ticket' => false, 'invoice' => false,
                'appointmentReminder' => false, 'unsubscribed' => true, 'blacklisted' => false,
            ],
            'insuranceProfile' => null,
            'derniereConsultation' => ['id' => 21, 'date' => '2026-03-04 11:12', 'motif' => 'Controle', 'statut' => 1],
            'impayees' => 230,
            'archiveFiles' => [['nom' => 'Fichier', 'url' => null], ['nom' => 'Scan', 'url' => '/scan.pdf']],
        ], $mapper->formatPatientSummary($patient, $consultation, 230));
    }

    public function testMappingUsesOnlyTheExplicitConsultationAndBalanceInputs(): void
    {
        $fallback = (new Consultation())->setStatut(1)->setNoteSeance('Fallback');
        $patient = $this->patient(7)->setDerniereConsultation($fallback);
        $supplied = (new Consultation())->setStatut(1)->setNoteSeance('Supplied');
        $mapper = new PatientSummaryMapper();

        $result = $mapper->formatPatientSummary($patient, $supplied, 125);
        self::assertSame('Supplied', $result['derniereConsultation']['motif']);
        self::assertSame(125, $result['impayees']);
        self::assertSame($result, $mapper->formatPatientSummary($patient, $supplied, 125));

        $withoutConsultation = $mapper->formatPatientSummary($patient, null, 0);
        self::assertNull($withoutConsultation['derniereConsultation']);
        self::assertSame(0, $withoutConsultation['impayees']);
        self::assertSame($fallback, $patient->getDerniereConsultation());
    }

    public function testPhotoMappingKeepsNonEmptyPathsAndNormalizesMissingPhotos(): void
    {
        $patient = $this->patient(7);
        $mapper = new PatientSummaryMapper();

        self::assertNull($mapper->resolvePatientPhoto($patient));
        $patient->setPhoto('');
        self::assertNull($mapper->resolvePatientPhoto($patient));
        $patient->setPhoto('/uploads/patients/7/photo.jpg');
        self::assertSame('/uploads/patients/7/photo.jpg', $mapper->resolvePatientPhoto($patient));
    }

    public function testInsuranceProfilePreservesCoverageFormAndInsurerProjection(): void
    {
        $assurance = (new Assurance())->setCode('INS')->setNom('Insurer')->setLogoPath('/logo.png')->setActif(false);
        $profile = (new PatientAssuranceProfile())->setAssurance($assurance)->setCoverageRate(80.0)->setFormData(['member' => 'ABC']);
        $patient = $this->patient(7)->setAssuranceProfile($profile);
        $mapper = new PatientSummaryMapper();

        self::assertSame([
            'id' => null, 'enabled' => true, 'coverageRate' => 80.0, 'formData' => ['member' => 'ABC'],
            'assurance' => ['id' => null, 'code' => 'INS', 'nom' => 'Insurer', 'logoPath' => '/logo.png', 'actif' => false],
        ], $mapper->formatInsuranceProfile($patient));
        $profile->setAssurance(null);
        self::assertNull($mapper->formatInsuranceProfile($patient)['assurance']);
    }
}
