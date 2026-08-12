<?php

namespace App\Patient\Infrastructure\Adapter;

use App\CareDelivery\Service\ConsultationService;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Patient\Application\Port\CreateConsultationForPatientPort;

final class LegacyCreateConsultationForPatientAdapter implements CreateConsultationForPatientPort
{
    public function __construct(private readonly ConsultationService $consultationService)
    {
    }

    public function create(array $data, ?object $actor = null): array
    {
        $user = $actor instanceof User ? $actor : null;

        return $this->consultationService->createConsultation($data, $user);
    }
}
