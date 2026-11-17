<?php

namespace App\Tests\Inventory;

use App\IdentityAccess\Domain\Entity\Employe;
use App\IdentityAccess\Domain\Entity\User;
use App\Inventory\Application\Handler\ConsommableQueryHandler;
use App\Inventory\Application\Mapper\ConsommableResponseMapper;
use App\Inventory\Application\Query\FetchStocksQuery;
use App\Inventory\Domain\Entity\Consommable;
use App\Inventory\Domain\Entity\Stock;
use App\Inventory\Domain\Repository\ConsommableRepositoryInterface;
use App\Inventory\Domain\Repository\StockRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ConsommableQueryHandlerTest extends TestCase
{
    /** @dataProvider thresholdCases */
    public function testSummaryUsesStrictThresholdAndDetailsDoNotIncludeFlag(int $quantity, bool $low): void
    {
        $c = (new Consommable())->setNom('Gants')->setQuantity($quantity)->setFournisseur(null)->setLowValue(3);
        $mapper = new ConsommableResponseMapper();
        $details = ['id' => null, 'nom' => 'Gants', 'quantity' => $quantity, 'fournisseur' => null, 'lowValue' => 3];
        self::assertSame($details, $mapper->details($c));
        self::assertSame($details + ['onlowvalue' => $low], $mapper->summary($c));
    }

    public static function thresholdCases(): iterable
    {
        yield [2, true];
        yield [3, false];
        yield [4, false];
    }

    /** @dataProvider stockNameCases */
    public function testStockMappingPreservesEmployeeThenUserFallback(bool $employee, bool $user, ?string $expectedName): void
    {
        $c = (new Consommable())->setNom('Gants');
        $s = (new Stock())->setConsommable($c)->setQuantiteUtilisee(2)->setType('Retrait')->setDescription('Soin')->setDatePrise(new \DateTime('2026-02-03 14:15:16'));
        if ($employee) {
            $s->setEmployee(new Employe());
        }
        if ($user) {
            $actor = $this->createMock(User::class);
            $actor->method('getUsername')->willReturn('alice');
            $s->setUser($actor);
        }
        self::assertSame([
            'consommable' => 'Gants', 'quantiteUtilisee' => 2, 'date' => '2026-02-03',
            'employe' => $expectedName, 'type' => 'Retrait', 'description' => 'Soin',
        ], (new ConsommableResponseMapper())->stock($s));
    }

    public static function stockNameCases(): iterable
    {
        yield 'no actor' => [false, false, 'N/A'];
        yield 'user' => [false, true, 'alice'];
        yield 'employee with null name still takes precedence' => [true, true, null];
    }

    public function testStockMappingUsesEmployeeNomNotFullName(): void
    {
        $s = (new Stock())->setConsommable((new Consommable())->setNom('Gants'))
            ->setEmployee((new Employe())->setNom('Doe')->setPrenom('Jane'))
            ->setDatePrise(new \DateTime('2026-02-03'));
        self::assertSame('Doe', (new ConsommableResponseMapper())->stock($s)['employe']);
    }

    /** @dataProvider dateCases */
    public function testHistoryDatesAndFilterArePassedToDomainRepository(?int $id, ?string $start, ?string $end): void
    {
        $stocks = $this->createMock(StockRepositoryInterface::class);
        $stocks->expects(self::once())->method('findHistory')->with(
            self::callback(fn (\DateTimeInterface $date) => $date->format('Y-m-d H:i:s') === (new \DateTime($start ?? '2 year ago'))->format('Y-m-d') . ' 00:00:00'),
            self::callback(fn (\DateTimeInterface $date) => $date->format('Y-m-d H:i:s') === (new \DateTime($end ?? 'today'))->format('Y-m-d') . ' 23:59:00'),
            $id,
        )->willReturn([]);
        $handler = new ConsommableQueryHandler($this->createMock(ConsommableRepositoryInterface::class), $stocks, new ConsommableResponseMapper());
        self::assertSame([], $handler->fetchStocks(new FetchStocksQuery($id, $start, $end)));
    }

    public static function dateCases(): iterable
    {
        yield 'defaults' => [null, null, null];
        yield 'explicit dates and id' => [42, '2026-01-02 12:34:56', '2026-02-03 10:11:12'];
        yield 'zero id' => [0, '2026-01-01', '2026-01-02'];
    }

    public function testHistoryMappingAlwaysReturnsASequentialList(): void
    {
        $stock = (new Stock())->setConsommable((new Consommable())->setNom('Gants'))
            ->setDatePrise(new \DateTime('2026-01-01'));
        $stocks = $this->createMock(StockRepositoryInterface::class);
        $stocks->method('findHistory')->willReturn([8 => $stock]);
        $handler = new ConsommableQueryHandler($this->createMock(ConsommableRepositoryInterface::class), $stocks, new ConsommableResponseMapper());
        self::assertSame([0], array_keys($handler->fetchStocks(new FetchStocksQuery())));
    }

    public function testInvalidDateStillThrowsInsteadOfReturningAnErrorArray(): void
    {
        $stocks = $this->createMock(StockRepositoryInterface::class);
        $stocks->expects(self::never())->method('findHistory');
        $handler = new ConsommableQueryHandler($this->createMock(ConsommableRepositoryInterface::class), $stocks, new ConsommableResponseMapper());
        $this->expectException(\Exception::class);
        $handler->fetchStocks(new FetchStocksQuery(null, 'not-a-date'));
    }
}
