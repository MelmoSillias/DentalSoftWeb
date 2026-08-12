<?php

namespace App\Inventory\Infrastructure\Adapter;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Inventory\Application\Port\InventoryWritePort;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\Consommable;
use App\Inventory\Infrastructure\Persistence\Doctrine\Repository\ConsommableRepository;
use App\Inventory\Service\ConsommableService;

final class LegacyInventoryWriteAdapter implements InventoryWritePort
{
    public function __construct(
        private readonly ConsommableService $consommableService,
        private readonly ConsommableRepository $consommableRepository,
    ) {
    }

    public function addConsommable(array $data, ?User $actor = null): array
    {
        return $this->consommableService->addConsommable($data, $actor);
    }

    public function editConsommable(int $id, array $data, ?User $actor = null): array
    {
        $consommable = $this->requireConsommable($id);

        return $this->consommableService->editConsommable($consommable, $data, $actor);
    }

    public function withdraw(int $id, array $data, ?User $actor = null): array
    {
        $consommable = $this->requireConsommable($id);

        return $this->consommableService->retrait($consommable, $data, $actor);
    }

    public function addStock(int $id, array $data, ?User $actor = null): array
    {
        $consommable = $this->requireConsommable($id);

        return $this->consommableService->addStock($consommable, $data, $actor);
    }

    public function deleteConsommable(int $id, ?User $actor = null): array
    {
        $consommable = $this->requireConsommable($id);

        return $this->consommableService->deleteConsommable($consommable, $actor);
    }

    private function requireConsommable(int $id): Consommable
    {
        $consommable = $this->consommableRepository->find($id);
        if (!$consommable instanceof Consommable) {
            throw new \RuntimeException('Consommable not found');
        }

        return $consommable;
    }
}
