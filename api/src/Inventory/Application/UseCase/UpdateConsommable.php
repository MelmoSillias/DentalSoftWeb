<?php

namespace App\Inventory\Application\UseCase;

use App\IdentityAccess\Domain\Entity\User;
use App\Inventory\Application\Service\ConsommableNotifications;
use App\Inventory\Domain\Entity\Consommable;
use Doctrine\ORM\EntityManagerInterface;

final class UpdateConsommable
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConsommableNotifications $notifications,
    ) {
    }

    public function execute(Consommable $consommable, array $data, ?User $actor = null): void
    {
        $consommable->setNom($data['nom'] ?? $consommable->getNom());
        if (isset($data['lowValue'])) {
            $consommable->setLowValue((int) $data['lowValue']);
        }
        if (isset($data['fournisseur'])) {
            $consommable->setFournisseur($data['fournisseur']);
        }
        $this->em->flush();

        $this->notifications->notifyAdminsForConsumable($consommable, 'updated', $actor);
        $this->notifications->notifyLowStock($consommable, $actor);
    }
}
