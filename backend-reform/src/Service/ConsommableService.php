<?php

namespace App\Service;

use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\User;
use App\Event\EntityActionEvent;
use App\Inventory\Entity\Consommable;
use App\Inventory\Entity\Stock;
use App\Inventory\Repository\ConsommableRepository;
use App\Inventory\Repository\StockRepository;
use App\IdentityAccess\Repository\EmployeRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ConsommableService
{
    private const CONSUMABLES_LINK = '/admin/consommables';

    public function __construct(
        private EntityManagerInterface $em,
        private ConsommableRepository $consRepo,
        private StockRepository $stockRepo,
        private EmployeRepository $employeRepo,
        private NotificationRecipientResolver $notificationRecipientResolver,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function listConsumablesWithVariations(): array
    {
        return [
            'consommables' => $this->consRepo->findAll(),
            'variations' => $this->stockRepo->findBy([], ['datePrise' => 'DESC']),
        ];
    }

    public function addConsommable(array $data, ?User $actor = null): array
    {
        $required = ['nom', 'quantite', 'fournisseur', 'lowValue'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['error' => "Champ $field manquant", 'status' => 400];
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

        $employee = $this->employeeFromUser($actor);
        if ($employee) {
            $stock->setEmployee($employee);
        }

        $this->em->persist($c);
        $this->em->persist($stock);
        $this->em->flush();

        $this->notifyAdminsForConsumable($c, 'created', $actor);
        $this->notifyLowStock($c, $actor);

        return ['message' => 'Consommable added successfully', 'status' => 201];
    }

    public function editConsommable(Consommable $consommable, array $data, ?User $actor = null): array
    {
        $consommable->setNom($data['nom'] ?? $consommable->getNom());
        if (isset($data['lowValue'])) {
            $consommable->setLowValue((int) $data['lowValue']);
        }
        if (isset($data['fournisseur'])) {
            $consommable->setFournisseur($data['fournisseur']);
        }
        $this->em->flush();

        $this->notifyAdminsForConsumable($consommable, 'updated', $actor);
        $this->notifyLowStock($consommable, $actor);

        return ['message' => 'Consommable updated successfully'];
    }

    public function retrait(Consommable $consommable, array $data, ?User $actor = null): array
    {
        $quantite = (int) ($data['quantite'] ?? 0);
        $description = $data['description'] ?? null;
        $employeId = $data['employe'] ?? null;
        $employe = $employeId ? $this->employeRepo->find($employeId) : null;

        if (!$employe) {
            return ['error' => 'Employé invalide.', 'status' => 400];
        }

        if ($quantite <= 0 || $quantite > $consommable->getQuantity()) {
            return ['error' => 'Quantité invalide.', 'status' => 400];
        }

        $consommable->setQuantity($consommable->getQuantity() - $quantite);
        $variation = new Stock();
        $variation->setConsommable($consommable);
        $variation->setQuantiteUtilisee($quantite);
        $variation->setType('Retrait');
        $variation->setDescription($description);
        $variation->setDatePrise(new DateTime());
        $variation->setEmployee($employe);
        $this->em->persist($variation);
        $this->em->flush();

        $this->notifyConsumableWithdraw($consommable, $employe, $quantite, $description, $actor);
        $this->notifyLowStock($consommable, $actor);

        return ['message' => 'Stock retired successfully'];
    }

    public function getConsommableDetails(Consommable $consommable): array
    {
        return [
            'id' => $consommable->getId(),
            'nom' => $consommable->getNom(),
            'quantity' => $consommable->getQuantity(),
            'fournisseur' => $consommable->getFournisseur(),
            'lowValue' => $consommable->getLowValue(),
        ];
    }

    public function addStock(Consommable $consommable, array $data, ?User $actor = null): array
    {
        $quantite = (int) ($data['quantite'] ?? 0);
        $description = $data['description'] ?? null;

        if ($quantite <= 0) {
            return ['error' => 'Quantité invalide.', 'status' => 400];
        }

        $consommable->setQuantity($consommable->getQuantity() + $quantite);
        
        $stock = new Stock();
        $stock->setConsommable($consommable);
        $stock->setQuantiteUtilisee($quantite);
        $stock->setType('Ajout');
        $stock->setDescription($description);
        $stock->setDatePrise(new DateTime());
        $employee = $this->employeeFromUser($actor);

        if ($employee) {
            $stock->setEmployee($employee);
        }

        $this->em->persist($stock);
        $this->em->flush();

        $this->notifyAdminsForConsumable($consommable, 'stock_added', $actor, $quantite);
        $this->notifyLowStock($consommable, $actor);

        return ['message' => 'Stock added successfully'];
    }

    public function deleteConsommable(Consommable $consommable, ?User $actor = null): array
    {
        $label = sprintf('%s (#%d)', $consommable->getNom(), $consommable->getId());

        $this->em->remove($consommable);
        $this->em->flush();

        $this->notifyAdminsForConsumable($consommable, 'deleted', $actor);

        return [
            'message' => sprintf('Consommable %s deleted successfully', $label),
            'status' => 200,
        ];
    }

    public function fetchStocks(?int $consumableId, ?string $startDate, ?string $endDate): array
    {
        $start = (new DateTime($startDate ?? "2 year ago"))->setTime(0, 0);
        $end = (new DateTime($endDate ?? 'today'))->setTime(23, 59);

        $queryBuilder = $this->stockRepo->createQueryBuilder('s')
            ->where('s.datePrise BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.datePrise', 'DESC');

        if ($consumableId) {
            $queryBuilder
                ->andWhere('s.consommable = :consumableId')
                ->setParameter('consumableId', $consumableId);
        }

        $stocks = $queryBuilder->getQuery()->getResult();

        $data = [];
        foreach ($stocks as $stock) {
            $data[] = [
                'consommable' => $stock->getConsommable()->getNom(),
                'quantiteUtilisee' => $stock->getQuantiteUtilisee(),
                'date' => $stock->getDatePrise()->format('Y-m-d'),
                'employe' => $stock->getEmployee() ? $stock->getEmployee()->getNom() : 'N/A',
                'type' => $stock->getType(),
                'description' => $stock->getDescription(),
            ];
        }

        return $data;
    }

    public function fetchConsommables(): array
    {
        $consommables = $this->consRepo->findAll();

        return array_map(function (Consommable $consommable) {
            return [
                'id' => $consommable->getId(),
                'nom' => $consommable->getNom(),
                'quantity' => $consommable->getQuantity(),
                'fournisseur' => $consommable->getFournisseur(),
                'lowValue' => $consommable->getLowValue(),
                'onlowvalue' => $consommable->getQuantity() < $consommable->getLowValue(),
            ];
        }, $consommables);
    }

    private function notifyAdminsForConsumable(
        Consommable $consommable,
        string $event,
        ?User $actor = null,
        ?int $quantityDelta = null,
    ): void {
        $recipients = $this->notificationRecipientResolver->admins($actor);

        if ($recipients === []) {
            return;
        }

        $name = $consommable->getNom();
        $quantity = $consommable->getQuantity();
        $priority = 'info';
        $type = 'info';

        $message = match ($event) {
            'created' => sprintf('Nouveau consommable %s ajouté (stock initial %d).', $name, $quantity),
            'updated' => sprintf('Consommable %s mis à jour (stock actuel %d).', $name, $quantity),
            'stock_added' => sprintf('Stock de %s augmenté de %d unités (total %d).', $name, (int) $quantityDelta, $quantity),
            'deleted' => sprintf('Consommable %s supprimé de l\'inventaire.', $name),
            default => null,
        };

        if ($message === null) {
            return;
        }

        if ($event === 'deleted') {
            $priority = 'warning';
            $type = 'warning';
        } elseif ($event === 'created') {
            $type = 'success';
        }

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $consommable,
                $event,
                ['ROLE_ADMIN'],
                $actor,
                [
                    'message' => $message,
                    'priority' => $priority,
                    'type' => $type,
                    'link' => self::CONSUMABLES_LINK,
                ],
            )
        );
    }

    private function notifyConsumableWithdraw(
        Consommable $consommable,
        Employe $employe,
        int $quantite,
        ?string $description,
        ?User $actor = null,
    ): void {
        $recipients = $this->notificationRecipientResolver->admins($actor);
        $employeeUser = $this->notificationRecipientResolver->userForEmploye($employe, $actor);

        if ($employeeUser) {
            $recipients[] = $employeeUser;
        }

        $recipients = $this->uniqueUsers($recipients);

        if ($recipients === []) {
            return;
        }

        $message = sprintf(
            'Retrait de %d %s par %s%s.',
            $quantite,
            $consommable->getNom(),
            $employe->getFullName(),
            $description ? ' - Motif: ' . $description : '',
        );

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $consommable,
                'stock_withdraw',
                ['ROLE_ADMIN'],
                $actor,
                [
                    'message' => $message,
                    'priority' => 'warning',
                    'type' => 'warning',
                    'link' => self::CONSUMABLES_LINK,
                ],
            )
        );
    }

    private function notifyLowStock(Consommable $consommable, ?User $actor = null): void
    {
        if ($consommable->getQuantity() > $consommable->getLowValue()) {
            return;
        }

        $recipients = $this->notificationRecipientResolver->admins($actor);
        if ($recipients === []) {
            return;
        }

        $priority = $consommable->getQuantity() <= 0
            ? 'critical'
            : 'warning';
        $type = $priority === 'critical'
            ? 'danger'
            : 'warning';

        $message = sprintf(
            'Stock bas pour %s : %d restants (seuil %d).',
            $consommable->getNom(),
            $consommable->getQuantity(),
            $consommable->getLowValue(),
        );

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $consommable,
                'low_stock',
                ['ROLE_ADMIN'],
                $actor,
                [
                    'message' => $message,
                    'priority' => $priority,
                    'type' => $type,
                    'link' => self::CONSUMABLES_LINK,
                ],
            )
        );
    }

    /**
     * @param list<User> $users
     * @return list<User>
     */
    private function uniqueUsers(array $users): array
    {
        $bucket = [];

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $bucket[$user->getId() ?? spl_object_id($user)] = $user;
        }

        return array_values($bucket);
    }

    private function employeeFromUser(?User $user): ?Employe
    {
        if (!$user) {
            return null;
        }

        return $this->employeRepo->findOneBy(['user' => $user]);
    }

    private function getListConsommables(): array
    {
        return $this->consRepo->findAll();
    }
}
