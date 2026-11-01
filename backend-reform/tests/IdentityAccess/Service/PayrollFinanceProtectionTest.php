<?php

namespace App\Tests\IdentityAccess\Service;

use App\Billing\Entity\ModeDePaiement;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\ChargeFixeRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Billing\Repository\TransactionRepository;
use App\Billing\Service\FinanceService;
use App\Billing\Service\LotFactureAssuranceService;
use App\Reporting\Service\ReportService;
use App\Settings\Service\GlobalSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PayrollFinanceProtectionTest extends TestCase
{
    private function service(Transaction $transaction, ?ModeDePaiement $mode = null): FinanceService
    {
        $transactions = $this->createMock(TransactionRepository::class);
        $transactions->method('find')->willReturn($transaction);
        $modes = $this->createMock(ModeDePaiementRepository::class);
        $modes->method('findActifs')->willReturn($mode ? [$mode] : []);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('remove');
        $manager->expects(self::never())->method('flush');
        return new FinanceService($this->createMock(ChargeFixeRepository::class), $modes, $transactions, $manager,
            $this->createMock(GlobalSettingsService::class), $this->createMock(LotFactureAssuranceService::class), $this->createMock(ReportService::class));
    }

    /** @dataProvider sources */
    public function testManagedTransactionsCannotBeEditedDeletedOrRejectedFromFinance(string $source): void
    {
        $transaction = (new Transaction())->setSource($source)->setType('Depense')->setMontant('10000')->markValidated();
        $service = $this->service($transaction);
        self::assertSame(400, $service->deleteTransaction(1)['status']);
        self::assertSame(400, $service->updateTransactionValidationStatus(1, 'rejected')['status']);
        self::assertSame(400, $service->updateTransaction(1, 'Depense', 20000, null, new \DateTimeImmutable(), 1)['status']);
        self::assertTrue($transaction->isValidated());
    }

    public function sources(): array { return [['payroll'], ['inps']]; }

    public function testCancelledTransactionIsExcludedFromCapitalAndMonthlyExpenses(): void
    {
        $mode = (new ModeDePaiement())->setLibelle('Espèces')->setType('cash');
        $old = (new Transaction())->setMontant('30000')->setType('Depense')->setDateTransaction(new \DateTimeImmutable('2026-03-01'));
        $old->cancel('Remplacée');
        $current = (new Transaction())->setMontant('25000')->setType('Depense')->setDateTransaction(new \DateTimeImmutable('2026-03-01'))->markValidated();
        $mode->getTransactions()->add($old);
        $mode->getTransactions()->add($current);
        $service = $this->service($current, $mode);
        self::assertEquals(-25000, $service->getSoldesParCompte()[0]['solde']);
        self::assertEquals(25000, $service->computeMonthlySummary([$old, $current])['monthlyExpenses'][2]);
    }
}
