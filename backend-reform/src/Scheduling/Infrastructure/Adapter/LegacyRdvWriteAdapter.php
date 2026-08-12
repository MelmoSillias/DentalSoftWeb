<?php

namespace App\Scheduling\Infrastructure\Adapter;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Scheduling\Application\Port\RdvWritePort;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\Rdv;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\RdvRepository as LegacyRdvRepository;
use App\Scheduling\Service\RdvService;

/**
 * Strangler write adapter: keeps consultation-from-rdv side effects inside RdvService.
 */
final class LegacyRdvWriteAdapter implements RdvWritePort
{
    public function __construct(
        private readonly RdvService $rdvService,
        private readonly LegacyRdvRepository $legacyRdvRepository,
    ) {
    }

    public function createRdv(array $data, ?object $actor = null): array
    {
        $user = $actor instanceof User ? $actor : null;

        return $this->rdvService->createRdv($data, $user);
    }

    public function handleAction(int $rdvId, string $action, array $payload, ?object $actor = null): array
    {
        $rdv = $this->legacyRdvRepository->find($rdvId);
        if (!$rdv instanceof Rdv) {
            return ['error' => 'RDV non trouvé', 'status' => 404];
        }

        $user = $actor instanceof User ? $actor : null;

        return $this->rdvService->handleAction($rdv, $action, $payload, $user);
    }
}
