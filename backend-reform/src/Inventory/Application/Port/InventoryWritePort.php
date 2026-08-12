<?php

namespace App\Inventory\Application\Port;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

interface InventoryWritePort
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function addConsommable(array $data, ?User $actor = null): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function editConsommable(int $id, array $data, ?User $actor = null): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function withdraw(int $id, array $data, ?User $actor = null): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function addStock(int $id, array $data, ?User $actor = null): array;

    /**
     * @return array<string, mixed>
     */
    public function deleteConsommable(int $id, ?User $actor = null): array;
}
