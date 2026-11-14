<?php

namespace App\Tests\Inventory;

use App\IdentityAccess\Domain\Entity\Employe;
use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Repository\EmployeRepositoryInterface;
use App\Inventory\Application\Exception\InvalidEmployee;
use App\Inventory\Application\Exception\InvalidStockQuantity;
use App\Inventory\Application\Exception\MissingConsommableField;
use App\Inventory\Application\Exception\UnauthenticatedInventoryActor;
use App\Inventory\Application\Handler\ConsommableQueryHandler;
use App\Inventory\Application\Mapper\ConsommableResponseMapper;
use App\Inventory\Application\Service\ConsommableNotifications;
use App\Inventory\Application\Service\ConsommableService;
use App\Inventory\Application\UseCase\CreateConsommable;
use App\Inventory\Application\UseCase\DeleteConsommable;
use App\Inventory\Application\UseCase\ReplenishStock;
use App\Inventory\Application\UseCase\UpdateConsommable;
use App\Inventory\Application\UseCase\WithdrawStock;
use App\Inventory\Domain\Entity\Consommable;
use App\Inventory\Domain\Entity\Stock;
use App\Inventory\Domain\Repository\ConsommableRepositoryInterface;
use App\Inventory\Domain\Repository\StockRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ConsommableServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private EmployeRepositoryInterface $employees;
    private ConsommableRepositoryInterface $consumables;
    private StockRepositoryInterface $stocks;
    private ConsommableNotifications $notifications;
    private ConsommableService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->employees = $this->createMock(EmployeRepositoryInterface::class);
        $this->consumables = $this->createMock(ConsommableRepositoryInterface::class);
        $this->stocks = $this->createMock(StockRepositoryInterface::class);
        $this->notifications = $this->createMock(ConsommableNotifications::class);
        $this->service = new ConsommableService(
            new ConsommableQueryHandler($this->consumables, $this->stocks, new ConsommableResponseMapper()),
            new CreateConsommable($this->em, $this->employees, $this->notifications),
            new UpdateConsommable($this->em, $this->notifications),
            new DeleteConsommable($this->em, $this->notifications),
            new WithdrawStock($this->em, $this->employees, $this->notifications),
            new ReplenishStock($this->em, $this->employees, $this->notifications),
        );
    }

    private function consumable(): Consommable
    {
        return (new Consommable())->setNom('Gants')->setQuantity(5)->setFournisseur('Supplier')->setLowValue(3);
    }

    private function expectNoWrites(): void
    {
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('remove');
        $this->em->expects(self::never())->method('flush');
        $this->notifications->expects(self::never())->method('notifyAdminsForConsumable');
        $this->notifications->expects(self::never())->method('notifyConsumableWithdraw');
        $this->notifications->expects(self::never())->method('notifyLowStock');
    }

    /** @dataProvider rejectionCases */
    public function testFacadePreservesRejectionsAndValidationOrder(string $operation, array $data, bool $employeeExists, bool $authenticated, array $expected): void
    {
        $this->expectNoWrites();
        $this->employees->method('find')->willReturn($employeeExists ? new Employe() : null);
        $c = $this->consumable();
        $actor = $authenticated ? new User() : null;

        $response = $operation === 'addConsommable'
            ? $this->service->addConsommable($data, $actor)
            : $this->service->$operation($c, $data, $actor);
        self::assertSame($expected, $response);
        self::assertSame(5, $c->getQuantity());
    }

    public static function rejectionCases(): iterable
    {
        $valid = ['nom' => 'Gants', 'quantite' => 2, 'fournisseur' => 'Supplier', 'lowValue' => 3];
        foreach (['nom', 'quantite', 'fournisseur', 'lowValue'] as $field) {
            $missing = $valid;
            unset($missing[$field]);
            yield 'missing ' . $field => ['addConsommable', $missing, false, false, ['error' => "Champ $field manquant", 'status' => 400]];
            $null = $valid;
            $null[$field] = null;
            yield 'null ' . $field => ['addConsommable', $null, false, false, ['error' => "Champ $field manquant", 'status' => 400]];
        }
        yield 'first missing field' => ['addConsommable', [], false, false, ['error' => 'Champ nom manquant', 'status' => 400]];
        yield 'create actor' => ['addConsommable', $valid, false, false, ['error' => 'Utilisateur non authentifié', 'status' => 401]];
        yield 'employee before quantity and actor' => ['retrait', ['employe' => 9, 'quantite' => -1], false, false, ['error' => 'Employé invalide.', 'status' => 400]];
        yield 'missing employee' => ['retrait', ['quantite' => 1], false, true, ['error' => 'Employé invalide.', 'status' => 400]];
        foreach ([0, -1, 6, 'not a number', null] as $quantity) {
            yield 'withdraw quantity ' . var_export($quantity, true) => ['retrait', ['employe' => 9, 'quantite' => $quantity], true, false, ['error' => 'Quantité invalide.', 'status' => 400]];
        }
        yield 'withdraw actor after valid quantity' => ['retrait', ['employe' => 9, 'quantite' => 5], true, false, ['error' => 'Utilisateur non authentifié', 'status' => 401]];
        foreach ([0, -1, 'invalid', null] as $quantity) {
            yield 'replenish quantity ' . var_export($quantity, true) => ['addStock', ['quantite' => $quantity], false, false, ['error' => 'Quantité invalide.', 'status' => 400]];
        }
        yield 'replenish actor' => ['addStock', ['quantite' => 1], false, false, ['error' => 'Utilisateur non authentifié', 'status' => 401]];
    }

    /** @dataProvider typedRejectionCases */
    public function testUseCasesThrowTypedRejections(string $case, string $exception): void
    {
        $this->expectNoWrites();
        $this->expectException($exception);
        if ($case === 'create') {
            (new CreateConsommable($this->em, $this->employees, $this->notifications))->execute([]);
        } elseif ($case === 'employee') {
            (new WithdrawStock($this->em, $this->employees, $this->notifications))->execute($this->consumable(), []);
        } else {
            (new ReplenishStock($this->em, $this->employees, $this->notifications))->execute($this->consumable(), ['quantite' => $case === 'quantity' ? 0 : 1]);
        }
    }

    public static function typedRejectionCases(): iterable
    {
        yield ['create', MissingConsommableField::class];
        yield ['employee', InvalidEmployee::class];
        yield ['quantity', InvalidStockQuantity::class];
        yield ['actor', UnauthenticatedInventoryActor::class];
    }

    /** @dataProvider successfulMutations */
    public function testSuccessfulMutationResponsesPersistenceAndNotificationOrder(string $operation, bool $withEmployee): void
    {
        $c = $this->consumable();
        (new \ReflectionProperty(Consommable::class, 'id'))->setValue($c, 42);
        $actor = new User();
        $employee = $withEmployee ? new Employe() : null;
        $this->employees->method('findOneBy')->with(['user' => $actor])->willReturn($employee);
        $this->employees->method('find')->with(9)->willReturn($employee);
        $trace = [];
        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$trace, &$persisted): void {
            $trace[] = $entity instanceof Stock ? 'persist stock' : 'persist consumable';
            $persisted[] = $entity;
        });
        $this->em->method('remove')->willReturnCallback(function (object $entity) use (&$trace, $c): void {
            self::assertSame($c, $entity);
            $trace[] = 'remove';
        });
        $this->em->expects(self::once())->method('flush')->willReturnCallback(function () use (&$trace, $operation, $c): void {
            $trace[] = 'flush';
            if ($operation === 'delete') {
                (new \ReflectionProperty(Consommable::class, 'id'))->setValue($c, null);
            }
        });
        $event = ['create' => 'created', 'update' => 'updated', 'delete' => 'deleted', 'replenish' => 'stock_added'][$operation] ?? null;
        $this->notifications->expects($event ? self::once() : self::never())->method('notifyAdminsForConsumable')
            ->willReturnCallback(function (Consommable $entity, string $action, ?User $emitter, ?int $delta = null) use (&$trace, $event, $operation, $actor): void {
                self::assertSame($event, $action);
                self::assertSame($actor, $emitter);
                self::assertSame($operation === 'replenish' ? 2 : null, $delta);
                $trace[] = $action;
            });
        $this->notifications->expects($operation === 'withdraw' ? self::once() : self::never())->method('notifyConsumableWithdraw')
            ->with($c, $employee, 5, 'Soin', $actor)->willReturnCallback(function () use (&$trace): void { $trace[] = 'stock_withdraw'; });
        $this->notifications->expects($operation === 'delete' ? self::never() : self::once())->method('notifyLowStock')
            ->willReturnCallback(function (Consommable $entity, ?User $emitter) use (&$trace, $actor): void {
                self::assertSame($actor, $emitter);
                $trace[] = 'low_stock';
            });

        $before = new \DateTime();
        $response = match ($operation) {
            'create' => $this->service->addConsommable(['nom' => 'Gants', 'quantite' => '2', 'fournisseur' => 'Supplier', 'lowValue' => '3'], $actor),
            'update' => $this->service->editConsommable($c, ['nom' => 'Masques', 'lowValue' => '4', 'fournisseur' => 'New', 'quantite' => 99], $actor),
            'delete' => $this->service->deleteConsommable($c, $actor),
            'withdraw' => $this->service->retrait($c, ['employe' => 9, 'quantite' => '5', 'description' => 'Soin'], $actor),
            'replenish' => $this->service->addStock($c, ['quantite' => '2', 'description' => 'Livraison'], $actor),
        };
        $expected = match ($operation) {
            'create' => ['message' => 'Consommable added successfully', 'status' => 201],
            'update' => ['message' => 'Consommable updated successfully'],
            'delete' => ['message' => 'Consommable Gants (#42) deleted successfully', 'status' => 200],
            'withdraw' => ['message' => 'Stock retired successfully'],
            'replenish' => ['message' => 'Stock added successfully'],
        };
        self::assertSame($expected, $response);
        self::assertSame(match ($operation) {
            'create' => ['persist consumable', 'persist stock', 'flush', 'created', 'low_stock'],
            'update' => ['flush', 'updated', 'low_stock'],
            'delete' => ['remove', 'flush', 'deleted'],
            'withdraw' => ['persist stock', 'flush', 'stock_withdraw', 'low_stock'],
            'replenish' => ['persist stock', 'flush', 'stock_added', 'low_stock'],
        }, $trace);

        if (in_array($operation, ['create', 'withdraw', 'replenish'], true)) {
            $stock = $persisted[count($persisted) - 1];
            self::assertInstanceOf(Stock::class, $stock);
            self::assertSame($actor, $stock->getUser());
            self::assertSame($employee, $stock->getEmployee());
            self::assertSame($operation === 'withdraw' ? 5 : 2, $stock->getQuantiteUtilisee());
            self::assertSame($operation === 'withdraw' ? 'Retrait' : 'Ajout', $stock->getType());
            self::assertSame(match ($operation) { 'create' => "Ajout d'un nouveau consommable", 'withdraw' => 'Soin', default => 'Livraison' }, $stock->getDescription());
            self::assertGreaterThanOrEqual($before, $stock->getDatePrise());
            self::assertLessThanOrEqual(new \DateTime(), $stock->getDatePrise());
            self::assertSame($operation === 'create' ? $persisted[0] : $c, $stock->getConsommable());
            self::assertSame(match ($operation) { 'create' => 2, 'withdraw' => 0, default => 7 }, $stock->getConsommable()->getQuantity());
        }
        if ($operation === 'update') {
            self::assertSame(['Masques', 5, 4, 'New'], [$c->getNom(), $c->getQuantity(), $c->getLowValue(), $c->getFournisseur()]);
        }
    }

    public static function successfulMutations(): iterable
    {
        yield 'create employee' => ['create', true];
        yield 'create no employee' => ['create', false];
        yield 'update' => ['update', false];
        yield 'delete' => ['delete', false];
        yield 'withdraw all stock' => ['withdraw', true];
        yield 'replenish employee' => ['replenish', true];
        yield 'replenish no employee' => ['replenish', false];
    }

    public function testUpdateRetainsNullFieldsAndDoesNotRequireActor(): void
    {
        $c = $this->consumable();
        $this->em->expects(self::once())->method('flush');
        self::assertSame(['message' => 'Consommable updated successfully'], $this->service->editConsommable($c, ['nom' => null, 'fournisseur' => null, 'lowValue' => null]));
        self::assertSame(['Gants', 'Supplier', 3], [$c->getNom(), $c->getFournisseur(), $c->getLowValue()]);
    }

    /** @dataProvider missingDescriptionCases */
    public function testNullDescriptionBugStillPropagatesAfterQuantityChange(string $operation, int $quantity): void
    {
        $this->expectNoWrites();
        $this->employees->method('find')->willReturn(new Employe());
        $c = $this->consumable();
        try {
            $this->service->$operation($c, ['employe' => 9, 'quantite' => 1], new User());
            self::fail('The existing string-only description setter must reject null.');
        } catch (\TypeError $error) {
            self::assertStringContainsString('setDescription', $error->getMessage());
            self::assertSame($quantity, $c->getQuantity());
        }
    }

    public static function missingDescriptionCases(): iterable
    {
        yield ['retrait', 4];
        yield ['addStock', 6];
    }

    public function testPersistenceFailurePropagatesWithoutNotifications(): void
    {
        $this->em->method('flush')->willThrowException(new \RuntimeException('Database failed'));
        $this->notifications->expects(self::never())->method('notifyAdminsForConsumable');
        $this->notifications->expects(self::never())->method('notifyLowStock');
        $this->expectExceptionMessage('Database failed');
        $this->service->editConsommable($this->consumable(), []);
    }

    /** @dataProvider initialQuantityCases */
    public function testCreatePreservesPermissiveInitialQuantityCoercion(mixed $quantity, int $expected): void
    {
        $persisted = [];
        $this->em->expects(self::exactly(2))->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $this->em->expects(self::once())->method('flush');
        self::assertSame(['message' => 'Consommable added successfully', 'status' => 201], $this->service->addConsommable([
            'nom' => 'Gants', 'quantite' => $quantity, 'fournisseur' => 'Supplier', 'lowValue' => 3,
        ], new User()));
        self::assertSame($expected, $persisted[0]->getQuantity());
        self::assertSame($expected, $persisted[1]->getQuantiteUtilisee());
    }

    public static function initialQuantityCases(): iterable
    {
        yield [0, 0];
        yield [-2, -2];
        yield ['not a number', 0];
    }

    public function testCreateStillValidatesEntityFieldsBeforeAuthentication(): void
    {
        $this->expectNoWrites();
        $this->expectException(\TypeError::class);
        $this->service->addConsommable(['nom' => [], 'quantite' => 1, 'fournisseur' => 'Supplier', 'lowValue' => 3]);
    }

    public function testDeleteDoesNotRequireActor(): void
    {
        $c = $this->consumable();
        $this->em->expects(self::once())->method('remove')->with($c);
        $this->em->expects(self::once())->method('flush');
        $this->notifications->expects(self::once())->method('notifyAdminsForConsumable')->with($c, 'deleted', null);
        $this->notifications->expects(self::never())->method('notifyLowStock');
        self::assertSame(['message' => 'Consommable Gants (#0) deleted successfully', 'status' => 200], $this->service->deleteConsommable($c));
    }

    public function testNotificationFailurePropagatesAfterFlushWithoutLowStockNotification(): void
    {
        $flushed = false;
        $this->em->expects(self::once())->method('flush')->willReturnCallback(function () use (&$flushed): void {
            $flushed = true;
        });
        $this->notifications->expects(self::once())->method('notifyAdminsForConsumable')->willReturnCallback(function () use (&$flushed): void {
            self::assertTrue($flushed);
            throw new \RuntimeException('Notification failed');
        });
        $this->notifications->expects(self::never())->method('notifyLowStock');
        $this->expectExceptionMessage('Notification failed');
        $this->service->editConsommable($this->consumable(), []);
    }

    public function testReadFacadeKeepsRawEntitiesAndMappedResponses(): void
    {
        $c = $this->consumable();
        $stock = (new Stock())->setConsommable($c)->setQuantiteUtilisee(1)->setType('Ajout')->setDescription('Delivery')->setDatePrise(new \DateTime('2026-01-02'));
        $this->consumables->method('findAll')->willReturn([$c]);
        $this->stocks->expects(self::once())->method('findBy')->with([], ['datePrise' => 'DESC'])->willReturn([$stock]);
        $this->stocks->expects(self::once())->method('findHistory')->willReturn([$stock]);
        self::assertSame(['consommables' => [$c], 'variations' => [$stock]], $this->service->listConsumablesWithVariations());
        $details = ['id' => null, 'nom' => 'Gants', 'quantity' => 5, 'fournisseur' => 'Supplier', 'lowValue' => 3];
        self::assertSame($details, $this->service->getConsommableDetails($c));
        self::assertSame([$details + ['onlowvalue' => false]], $this->service->fetchConsommables());
        self::assertSame([['consommable' => 'Gants', 'quantiteUtilisee' => 1, 'date' => '2026-01-02', 'employe' => 'N/A', 'type' => 'Ajout', 'description' => 'Delivery']], $this->service->fetchStocks(null, null, null));
    }
}
