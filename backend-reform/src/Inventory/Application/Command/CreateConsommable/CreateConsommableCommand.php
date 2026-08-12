<?php

namespace App\Inventory\Application\Command\CreateConsommable;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class CreateConsommableCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly ?User $actor = null,
    ) {
    }
}
