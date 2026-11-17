<?php

namespace App\Patient\Application\ReadModel;

use App\Billing\Infrastructure\Persistence\Doctrine\FactureRepository;
use App\CareDelivery\Domain\Entity\Consultation;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\ConsultationRepository;
use App\Patient\Application\Mapper\PatientSummaryMapper;
use App\Patient\Domain\Entity\Patient;

final class PatientSummaryProvider
{
    public function __construct(
        private ConsultationRepository $consultationRepo,
        private FactureRepository $factureRepo,
        private PatientSummaryMapper $mapper,
    ) {
    }

    public function getSummary(Patient $patient): array
    {
        $consultation = $this->resolveLatestConsultation($patient);
        $impayees = $this->getPatientImpayees((int) $patient->getId());

        return $this->mapper->formatPatientSummary($patient, $consultation, $impayees);
    }

    /**
     * @param array<array-key, Patient> $patients
     * @return array<array-key, array>
     */
    public function getSummaries(array $patients): array
    {
        if ($patients === []) {
            return [];
        }

        $ids = array_map(static fn (Patient $patient): int => (int) $patient->getId(), $patients);
        $impayeesByPatientId = $this->factureRepo->sumUnpaidResteByPatientIds($ids);

        return array_map(
            fn (Patient $patient): array => $this->mapper->formatPatientSummary(
                $patient,
                $this->resolveLatestConsultation($patient),
                (int) ($impayeesByPatientId[(int) $patient->getId()] ?? 0),
            ),
            $patients,
        );
    }

    public function resolveLatestConsultation(Patient $patient): ?Consultation
    {
        $latest = $this->consultationRepo->findLatestClosedByPatient($patient);

        if ($latest !== null) {
            return $latest;
        }

        $fallback = $patient->getDerniereConsultation();
        if ($fallback instanceof Consultation && (int) $fallback->getStatut() === 1) {
            return $fallback;
        }

        return null;
    }

    public function getPatientImpayees(int $id): int
    {
        $sums = $this->factureRepo->sumUnpaidResteByPatientIds([$id]);

        return (int) ($sums[$id] ?? 0);
    }
}
