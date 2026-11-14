<?php

namespace App\Tests\Inventory;

use App\Communication\Application\Service\NotificationLinkBuilder;
use App\Communication\Application\Service\NotificationRecipientResolver;
use App\IdentityAccess\Domain\Entity\Employe;
use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\Inventory\Application\Service\ConsommableNotifications;
use App\Inventory\Domain\Entity\Consommable;
use App\SharedKernel\Domain\Event\EntityActionEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ConsommableNotificationsTest extends TestCase
{
    private function user(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('isActive')->willReturn(true);
        $user->method('isNotificationsEnabled')->willReturn(true);

        return $user;
    }

    private function notifications(array $admins, array &$events): ConsommableNotifications
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByRoles')->with(['ROLE_ADMIN'])->willReturn($admins);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(EntityActionEvent::class, function (EntityActionEvent $event) use (&$events): void {
            $events[] = $event;
        });

        return new ConsommableNotifications(new NotificationRecipientResolver($users), $dispatcher);
    }

    private function consumable(int $quantity = 3): Consommable
    {
        return (new Consommable())->setNom('Gants')->setQuantity($quantity)->setLowValue(3);
    }

    /** @dataProvider adminEventCases */
    public function testAdminEventPayloads(string $action, ?int $delta, string $message, string $priority, string $type): void
    {
        $events = [];
        $notifications = $this->notifications([$this->user(1)], $events);
        $actor = $this->user(2);
        $c = $this->consumable();
        $notifications->notifyAdminsForConsumable($c, $action, $actor, $delta);

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertSame($c, $event->getEntity());
        self::assertSame($actor, $event->getEmitter());
        self::assertSame($action, $event->getActionType());
        self::assertSame(['ROLE_ADMIN'], $event->getTargetRoles());
        self::assertSame([
            'message' => $message, 'priority' => $priority, 'type' => $type,
            'link' => NotificationLinkBuilder::ADMIN_CONSUMABLES,
        ], $event->getContext());
    }

    public static function adminEventCases(): iterable
    {
        yield ['created', null, 'Nouveau consommable Gants ajouté (stock initial 3).', 'info', 'success'];
        yield ['updated', null, 'Consommable Gants mis à jour (stock actuel 3).', 'info', 'info'];
        yield ['stock_added', 2, 'Stock de Gants augmenté de 2 unités (total 3).', 'info', 'info'];
        yield ['deleted', null, "Consommable Gants supprimé de l'inventaire.", 'warning', 'warning'];
    }

    /** @dataProvider lowStockCases */
    public function testLowStockNotificationUsesInclusiveThreshold(int $quantity, ?string $priority, ?string $type): void
    {
        $events = [];
        $notifications = $this->notifications([$this->user(1)], $events);
        $notifications->notifyLowStock($this->consumable($quantity));
        if ($priority === null) {
            self::assertSame([], $events);
            return;
        }
        self::assertCount(1, $events);
        self::assertSame('low_stock', $events[0]->getActionType());
        self::assertNull($events[0]->getEmitter());
        self::assertSame([
            'message' => sprintf('Stock bas pour Gants : %d restants (seuil 3).', $quantity),
            'priority' => $priority, 'type' => $type,
            'link' => NotificationLinkBuilder::ADMIN_CONSUMABLES,
        ], $events[0]->getContext());
    }

    public static function lowStockCases(): iterable
    {
        yield 'above' => [4, null, null];
        yield 'equal' => [3, 'warning', 'warning'];
        yield 'below' => [2, 'warning', 'warning'];
        yield 'empty' => [0, 'critical', 'danger'];
        yield 'negative' => [-1, 'critical', 'danger'];
    }

    public function testNoEligibleRecipientsAndUnknownActionsDoNotDispatch(): void
    {
        $events = [];
        $actor = $this->user(1);
        $notifications = $this->notifications([$actor], $events);
        $c = $this->consumable();
        $notifications->notifyAdminsForConsumable($c, 'created', $actor);
        $notifications->notifyLowStock($c, $actor);
        $notifications->notifyConsumableWithdraw($c, (new Employe())->setUser($actor), 1, null, $actor);
        self::assertSame([], $events);

        $notifications = $this->notifications([$actor], $events);
        $notifications->notifyAdminsForConsumable($c, 'unknown');
        self::assertSame([], $events);
    }

    /** @dataProvider descriptionCases */
    public function testWithdrawalCanDispatchForEmployeeOnlyAndKeepsOriginalRoleTarget(?string $description, string $suffix): void
    {
        $events = [];
        $notifications = $this->notifications([], $events);
        $employee = (new Employe())->setNom('Doe')->setPrenom('Jane')->setUser($this->user(1));
        $actor = $this->user(2);
        $c = $this->consumable();
        $notifications->notifyConsumableWithdraw($c, $employee, 2, $description, $actor);

        self::assertCount(1, $events);
        self::assertSame('stock_withdraw', $events[0]->getActionType());
        self::assertSame($c, $events[0]->getEntity());
        self::assertSame($actor, $events[0]->getEmitter());
        self::assertSame(['ROLE_ADMIN'], $events[0]->getTargetRoles());
        self::assertSame([
            'message' => sprintf('Retrait de 2 Gants par %s%s.', $employee->getFullName(), $suffix),
            'priority' => 'warning', 'type' => 'warning',
            'link' => NotificationLinkBuilder::ADMIN_CONSUMABLES,
        ], $events[0]->getContext());
    }

    public static function descriptionCases(): iterable
    {
        yield ['Soin', ' - Motif: Soin'];
        yield ['', ''];
        yield [null, ''];
        yield ['0', ''];
    }
}
