<?php

namespace App\Inventory\Application\Command\DeleteConsommable;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class DeleteConsommableCommand
{
    public function __construct(
        public readonly int $id,
        public readonly ?User $actor = null,
    ) {
    }
}
