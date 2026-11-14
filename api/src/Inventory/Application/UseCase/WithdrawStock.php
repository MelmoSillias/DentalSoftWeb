<?php

namespace App\Inventory\Application\UseCase;

use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Repository\EmployeRepositoryInterface;
use App\Inventory\Application\Exception\InvalidEmployee;
use App\Inventory\Application\Exception\InvalidStockQuantity;
use App\Inventory\Application\Exception\UnauthenticatedInventoryActor;
use App\Inventory\Application\Service\ConsommableNotifications;
use App\Inventory\Domain\Entity\Consommable;
use App\Inventory\Domain\Entity\Stock;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

final class WithdrawStock
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
        $employeId = $data['employe'] ?? null;
        $employe = $employeId ? $this->employeRepo->find($employeId) : null;

        if (!$employe) {
            throw new InvalidEmployee();
        }
        if ($quantite <= 0 || $quantite > $consommable->getQuantity()) {
            throw new InvalidStockQuantity();
        }
        if (!$actor) {
            throw new UnauthenticatedInventoryActor();
        }

        $consommable->setQuantity($consommable->getQuantity() - $quantite);
        $variation = new Stock();
        $variation->setConsommable($consommable);
        $variation->setQuantiteUtilisee($quantite);
        $variation->setType('Retrait');
        $variation->setDescription($description);
        $variation->setDatePrise(new DateTime());
        $variation->setUser($actor);
        $variation->setEmployee($employe);
        $this->em->persist($variation);
        $this->em->flush();

        $this->notifications->notifyConsumableWithdraw($consommable, $employe, $quantite, $description, $actor);
        $this->notifications->notifyLowStock($consommable, $actor);
    }
}
