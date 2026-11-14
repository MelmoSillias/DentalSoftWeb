<?php

namespace App\Inventory\Application\UseCase;

use App\IdentityAccess\Domain\Entity\User;
use App\Inventory\Application\Service\ConsommableNotifications;
use App\Inventory\Domain\Entity\Consommable;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteConsommable
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConsommableNotifications $notifications,
    ) {
    }

    public function execute(Consommable $consommable, ?User $actor = null): string
    {
        $label = sprintf('%s (#%d)', $consommable->getNom(), $consommable->getId());

        $this->em->remove($consommable);
        $this->em->flush();

        $this->notifications->notifyAdminsForConsumable($consommable, 'deleted', $actor);

        return $label;
    }
}
