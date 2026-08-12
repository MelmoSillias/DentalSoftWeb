<?php

namespace App\Inventory\Application\Command\AddConsommableStock;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class AddConsommableStockCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly int $id,
        public readonly array $data,
        public readonly ?User $actor = null,
    ) {
    }
}
