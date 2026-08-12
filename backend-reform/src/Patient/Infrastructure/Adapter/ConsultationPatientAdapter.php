<?php

namespace App\Patient\Infrastructure\Adapter;

use App\CareDelivery\Application\Port\ConsultationPatientPort;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use App\Patient\Infrastructure\Persistence\Doctrine\Repository\PatientRepository;

final class ConsultationPatientAdapter implements ConsultationPatientPort
{
    public function __construct(
        private readonly PatientRepository $patientRepo,
    ) {
    }

    public function findPatient(int $id): ?object
    {
        return $this->patientRepo->find($id);
    }

    public function hasActiveInsurance(object $patient): bool
    {
        if (!$patient instanceof Patient) {
            return false;
        }

        $profile = $patient->getAssuranceProfile();
        $assurance = $profile?->getAssurance();

        return $profile !== null && $assurance !== null && $assurance->isActif();
    }

    public function getInsuranceProfile(object $patient): ?array
    {
        if (!$patient instanceof Patient || !$this->hasActiveInsurance($patient)) {
            return null;
        }

        $assurance = $patient->getAssuranceProfile()?->getAssurance();

        return [
            'enabled' => true,
            'coverageRate' => $patient->getAssuranceProfile()?->getCoverageRate(),
            'assurance' => [
                'id' => $assurance?->getId(),
                'nom' => $assurance?->getNom(),
                'code' => $assurance?->getCode(),
            ],
        ];
    }
}
