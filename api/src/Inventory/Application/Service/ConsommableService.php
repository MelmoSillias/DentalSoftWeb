<?php

namespace App\Inventory\Application\Service;

use App\IdentityAccess\Domain\Entity\User;
use App\Inventory\Application\Exception\InventoryRejection;
use App\Inventory\Application\Exception\UnauthenticatedInventoryActor;
use App\Inventory\Application\Handler\ConsommableQueryHandler;
use App\Inventory\Application\Query\FetchStocksQuery;
use App\Inventory\Application\UseCase\CreateConsommable;
use App\Inventory\Application\UseCase\DeleteConsommable;
use App\Inventory\Application\UseCase\ReplenishStock;
use App\Inventory\Application\UseCase\UpdateConsommable;
use App\Inventory\Application\UseCase\WithdrawStock;
use App\Inventory\Domain\Entity\Consommable;

class ConsommableService
{
    public function __construct(
        private ConsommableQueryHandler $queries,
        private CreateConsommable $create,
        private UpdateConsommable $update,
        private DeleteConsommable $delete,
        private WithdrawStock $withdraw,
        private ReplenishStock $replenish,
    ) {
    }

    public function listConsumablesWithVariations(): array
    {
        return $this->queries->listWithVariations();
    }

    public function addConsommable(array $data, ?User $actor = null): array
    {
        return $this->withRejectionMapping(
            fn () => $this->create->execute($data, $actor),
            ['message' => 'Consommable added successfully', 'status' => 201],
        );
    }

    public function editConsommable(Consommable $consommable, array $data, ?User $actor = null): array
    {
        $this->update->execute($consommable, $data, $actor);

        return ['message' => 'Consommable updated successfully'];
    }

    public function retrait(Consommable $consommable, array $data, ?User $actor = null): array
    {
        return $this->withRejectionMapping(
            fn () => $this->withdraw->execute($consommable, $data, $actor),
            ['message' => 'Stock retired successfully'],
        );
    }

    public function getConsommableDetails(Consommable $consommable): array
    {
        return $this->queries->details($consommable);
    }

    public function addStock(Consommable $consommable, array $data, ?User $actor = null): array
    {
        return $this->withRejectionMapping(
            fn () => $this->replenish->execute($consommable, $data, $actor),
            ['message' => 'Stock added successfully'],
        );
    }

    public function deleteConsommable(Consommable $consommable, ?User $actor = null): array
    {
        $label = $this->delete->execute($consommable, $actor);

        return [
            'message' => sprintf('Consommable %s deleted successfully', $label),
            'status' => 200,
        ];
    }

    public function fetchStocks(?int $consumableId, ?string $startDate, ?string $endDate): array
    {
        return $this->queries->fetchStocks(new FetchStocksQuery($consumableId, $startDate, $endDate));
    }

    public function fetchConsommables(): array
    {
        return $this->queries->fetchConsommables();
    }

    private function withRejectionMapping(callable $operation, array $success): array
    {
        try {
            $operation();
        } catch (InventoryRejection $rejection) {
            return [
                'error' => $rejection->getMessage(),
                'status' => $rejection instanceof UnauthenticatedInventoryActor ? 401 : 400,
            ];
        }

        return $success;
    }
}
