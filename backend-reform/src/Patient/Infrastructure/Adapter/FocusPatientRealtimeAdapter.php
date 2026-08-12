<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Focus\Service\FocusRealtimePublisher;
use App\Patient\Application\Port\PatientRealtimePort;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;

final class FocusPatientRealtimeAdapter implements PatientRealtimePort
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FocusRealtimePublisher $focusRealtimePublisher,
    ) {
    }

    public function publishPatientRefresh(int $patientId, string $action): void
    {
        $patient = $this->em->find(Patient::class, $patientId);
        if (!$patient instanceof Patient) {
            return;
        }

        $this->focusRealtimePublisher->publishPatientRefresh($patient, $action);
    }
}
