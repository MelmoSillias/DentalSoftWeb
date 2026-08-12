<?php

namespace App\Inventory\Application\Command\WithdrawConsommable;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class WithdrawConsommableCommand
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
