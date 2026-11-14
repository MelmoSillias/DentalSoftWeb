<?php

namespace App\Inventory\Application\UseCase;

use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Repository\EmployeRepositoryInterface;
use App\Inventory\Application\Exception\MissingConsommableField;
use App\Inventory\Application\Exception\UnauthenticatedInventoryActor;
use App\Inventory\Application\Service\ConsommableNotifications;
use App\Inventory\Domain\Entity\Consommable;
use App\Inventory\Domain\Entity\Stock;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

final class CreateConsommable
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmployeRepositoryInterface $employeRepo,
        private ConsommableNotifications $notifications,
    ) {
    }

    public function execute(array $data, ?User $actor = null): void
    {
        foreach (['nom', 'quantite', 'fournisseur', 'lowValue'] as $field) {
            if (!isset($data[$field])) {
                throw new MissingConsommableField($field);
            }
        }

        $c = new Consommable();
        $c->setNom($data['nom']);
        $c->setQuantity((int) ($data['quantite'] ?? 0));
        $c->setFournisseur($data['fournisseur']);
        $c->setLowValue((int) $data['lowValue']);

        $stock = new Stock();
        $stock->setConsommable($c);
        $stock->setQuantiteUtilisee($c->getQuantity());
        $stock->setType('Ajout');
        $stock->setDescription("Ajout d'un nouveau consommable");
        $stock->setDatePrise(new DateTime());

        if (!$actor) {
            throw new UnauthenticatedInventoryActor();
        }

        $stock->setUser($actor);
        $employee = $this->employeRepo->findOneBy(['user' => $actor]);
        if ($employee) {
            $stock->setEmployee($employee);
        }

        $this->em->persist($c);
        $this->em->persist($stock);
        $this->em->flush();

        $this->notifications->notifyAdminsForConsumable($c, 'created', $actor);
        $this->notifications->notifyLowStock($c, $actor);
    }
}
