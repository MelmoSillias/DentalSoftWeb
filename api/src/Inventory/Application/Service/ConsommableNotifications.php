<?php

namespace App\Inventory\Application\Service;

use App\Communication\Application\Service\NotificationLinkBuilder;
use App\Communication\Application\Service\NotificationRecipientResolver;
use App\IdentityAccess\Domain\Entity\Employe;
use App\IdentityAccess\Domain\Entity\User;
use App\Inventory\Domain\Entity\Consommable;
use App\SharedKernel\Domain\Event\EntityActionEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ConsommableNotifications
{
    private const CONSUMABLES_LINK = NotificationLinkBuilder::ADMIN_CONSUMABLES;

    public function __construct(
        private NotificationRecipientResolver $notificationRecipientResolver,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function notifyAdminsForConsumable(
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

    public function notifyConsumableWithdraw(
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

    public function notifyLowStock(Consommable $consommable, ?User $actor = null): void
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
}
