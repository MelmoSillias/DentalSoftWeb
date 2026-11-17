<?php

namespace App\Inventory\Application\UseCase;

use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Repository\EmployeRepositoryInterface;
use App\Inventory\Application\Exception\InvalidStockQuantity;
use App\Inventory\Application\Exception\UnauthenticatedInventoryActor;
use App\Inventory\Application\Service\ConsommableNotifications;
use App\Inventory\Domain\Entity\Consommable;
use App\Inventory\Domain\Entity\Stock;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

final class ReplenishStock
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmployeRepositoryInterface $employeRepo,
        private ConsommableNotifications $notifications,
    ) {
    }

    public function execute(Consommable $consommable, array $data, ?User $actor = null): void
    {
        $quantite = (int) ($data['quantite'] ?? 0);
        $description = $data['description'] ?? null;

        if ($quantite <= 0) {
            throw new InvalidStockQuantity();
        }
        if (!$actor) {
            throw new UnauthenticatedInventoryActor();
        }

        $consommable->setQuantity($consommable->getQuantity() + $quantite);

        $stock = new Stock();
        $stock->setConsommable($consommable);
        $stock->setQuantiteUtilisee($quantite);
        $stock->setType('Ajout');
        $stock->setDescription($description);
        $stock->setDatePrise(new DateTime());
        $stock->setUser($actor);
        $employee = $this->employeRepo->findOneBy(['user' => $actor]);
        if ($employee) {
            $stock->setEmployee($employee);
        }

        $this->em->persist($stock);
        $this->em->flush();

        $this->notifications->notifyAdminsForConsumable($consommable, 'stock_added', $actor, $quantite);
        $this->notifications->notifyLowStock($consommable, $actor);
    }
}
