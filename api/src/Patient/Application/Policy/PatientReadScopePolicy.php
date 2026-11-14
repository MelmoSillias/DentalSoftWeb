<?php

namespace App\Patient\Application\Policy;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\EmployeRepository;
use App\Settings\Application\Service\GlobalSettingsService;

final class PatientReadScopePolicy
{
    public function __construct(
        private EmployeRepository $employeRepo,
        private GlobalSettingsService $globalSettingsService,
    ) {
    }

    public function restrictToMedecin(bool $medecinOnly): bool
    {
        return $medecinOnly && !$this->globalSettingsService->isMedecinCreatePatientAndConsultationAllowed();
    }

    public function resolveMedecinFromUser(?object $user): array
    {
        if (!$user) {
            return ['error' => 'Utilisateur non authentifié', 'status' => 401];
        }

        $employe = $this->employeRepo->findOneBy(['user' => $user]);
        if (!$employe) {
            return ['error' => 'Aucun employé associé', 'status' => 404];
        }

        return ['employe' => $employe];
    }
}
