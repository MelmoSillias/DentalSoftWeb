<?php

namespace App\Tests\Billing;

use App\Billing\Application\Handler\FinanceAccountQueryHandler;
use App\Billing\Application\Handler\FinanceCrossTableQueryHandler;
use App\Billing\Application\Mapper\FinanceTransactionMapper;
use App\Billing\Application\Policy\ManualTransactionPolicy;
use App\Billing\Application\Policy\TransactionTypePolicy;
use App\Billing\Domain\Entity\ModeDePaiement;
use App\Billing\Domain\Entity\Transaction;
use App\Billing\Infrastructure\Persistence\Doctrine\ModeDePaiementRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\TransactionRepository;
use App\Reporting\Application\Service\ReportService;
use App\Settings\Application\Service\GlobalSettingsService;
use PHPUnit\Framework\TestCase;

class FinanceReportingRegressionTest extends TestCase
{
    private function transaction(string $type, string $amount, string $date, ?string $validatedAt = null): Transaction
    {
        $transaction = (new Transaction())->setType($type)->setMontant($amount)
            ->setDateTransaction(new \DateTimeImmutable($date))
            ->setModeDePaiement((new ModeDePaiement())->setLibelle('Cash')->setType('cash'));
        if ($validatedAt !== null) $transaction->markValidated(new \DateTimeImmutable($validatedAt));
        return $transaction;
    }

    private function crossTables($repo, $reports = null, $settings = null): FinanceCrossTableQueryHandler
    {
        $policy = new TransactionTypePolicy();
        return new FinanceCrossTableQueryHandler($repo, $reports ?? $this->createMock(ReportService::class),
            $settings ?? $this->createMock(GlobalSettingsService::class),
            new FinanceTransactionMapper($policy, new ManualTransactionPolicy()), $policy);
    }

    public function testAccountReportsKeepTheirDistinctLegacyFilteringRules(): void
    {
        $transactions = [
            $this->transaction('Revenue', '100', '2026-02-01', '2026-03-01'),
            $this->transaction('Depense', '20', '2026-02-02', '2026-03-01'),
            $this->transaction('other', '5', '2026-02-03', '2026-03-01'),
            $this->transaction('Revenue', '50', '2026-02-04'),
            $this->transaction('Revenue', '10', '2025-01-01', '2025-01-01'),
        ];
        $mode = (new ModeDePaiement())->setLibelle('Cash')->setType('cash');
        foreach ($transactions as $transaction) $mode->getTransactions()->add($transaction);
        $modes = $this->createMock(ModeDePaiementRepository::class);
        $modes->method('findActifs')->willReturn([$mode]);
        $repo = $this->createMock(TransactionRepository::class);
        $repo->method('findAll')->willReturn($transactions);
        $handler = new FinanceAccountQueryHandler($modes, $repo);
        $summary = $handler->computeMonthlySummary($transactions);
        self::assertSame(100.0, $summary['monthlyRevenues'][1]);
        self::assertSame(25.0, $summary['monthlyExpenses'][1]);
        self::assertSame(75.0, $summary['monthlyProfits'][1]);
        self::assertSame([2026, 2025], $handler->getAvailableTransactionYears());
        self::assertEquals([100], $handler->getBarParCompteAnnuel(2026)['entrees']);
        self::assertEquals([25], $handler->getBarPointChartData(2026)['depenses']);
        self::assertEquals(85, $handler->getSoldesParCompte()[0]['solde']);
        $datasets = $handler->getGraphDatasetsParCompteComplet(2026);
        self::assertEquals(20, $datasets[1]['data'][1]);
        self::assertEquals(80, $datasets[2]['data'][1]);
        // Annual capital intentionally retains pending rows and repository iteration order.
        self::assertEquals(125, $handler->getEvolutionCapitalAnnuel(2026)[1]);
        self::assertSame(0, $handler->getEvolutionCapitalAnnuel(2026)[2]);
    }

    public function testMonthlyCrossTableUsesValidationDateAndSevenDayBuckets(): void
    {
        $repo = $this->createMock(TransactionRepository::class);
        $repo->expects(self::once())->method('findValidatedBetweenByTypes')
            ->with(new \DateTimeImmutable('2026-02-01 00:00:00'), new \DateTimeImmutable('2026-02-28 23:59:59'), ['Depense'])
            ->willReturn([
                $this->transaction('Depense', '10.5', '2026-01-01', '2026-02-07'),
                $this->transaction('Depense', '20', '2026-01-01', '2026-02-08'),
                $this->transaction('Depense', '30', '2026-01-01', '2026-02-28'),
                $this->transaction('Depense', '999', '2026-02-01'),
            ]);
        $settings = $this->createMock(GlobalSettingsService::class);
        $settings->method('getTransactionMotifs')->willReturn(['Rent']);
        $result = $this->crossTables($repo, null, $settings)->getMonthlyCrossTable(2026, 2, 'expense');
        self::assertSame('expense', $result['type']);
        self::assertCount(4, $result['weeks']);
        self::assertSame([10.5, 0.0, 0.0, 30.0], $result['rows'][5]['values']);
        self::assertSame([0.0, 20.0, 0.0, 0.0], $result['rows'][6]['values']);
        self::assertSame([10.5, 20.0, 0.0, 30.0], $result['columnTotals']);
        self::assertSame(60.5, $result['grandTotal']);
        self::assertSame(['Rent'], $result['transactionMotifs']);
    }

    public function testPeriodOverviewPreservesReportPayloadAndRoundedTotals(): void
    {
        $from = new \DateTimeImmutable('2026-02-01 00:00:00');
        $to = new \DateTimeImmutable('2026-02-02 23:59:59');
        $repo = $this->createMock(TransactionRepository::class);
        $repo->expects(self::once())->method('findValidatedBetweenByTypes')->with($from, $to, ['Revenue', 'Depense'])
            ->willReturn([$this->transaction('Revenue', '10.555', '2026-02-01', '2026-02-01'),
                $this->transaction('Depense', '2.224', '2026-02-02', '2026-02-02')]);
        $reports = $this->createMock(ReportService::class);
        $doctors = [['name' => 'Doctor', 'actes' => [['date' => '2026-02-01', 'patient' => 'Patient', 'montant' => 25]]]];
        $reports->expects(self::once())->method('periodicDoctorReports')->with($from, $to)->willReturn(['doctors' => $doctors, 'kpi' => ['total' => 25]]);
        $reports->expects(self::once())->method('periodicActsStats')->with(\DateTime::createFromImmutable($from), \DateTime::createFromImmutable($to))
            ->willReturn(['categories' => [['items' => [['label' => 'A', 'value' => 1], ['label' => 'B', 'value' => 3]]]]]);
        $reports->method('periodicPatients')->willReturn(['total' => 4]);
        $reports->method('periodicAppointments')->willReturn(['total' => 5]);
        $reports->method('periodicConsultations')->willReturn(['total' => 6, 'paid' => 2, 'free' => 4]);
        $reports->method('getReceptionStats')->willReturn(['pendingConsultations' => 7]);
        $result = $this->crossTables($repo, $reports)->getCrossTablePeriodOverview('2026-02-01', '2026-02-02');
        self::assertSame(['revenue' => 10.56, 'expense' => 2.22], $result['totals']);
        self::assertSame('01/02/2026 - 02/02/2026', $result['dateLabel']);
        self::assertSame(['total' => 6, 'paid' => 2, 'free' => 4, 'pending' => 7], $result['consultations']);
        self::assertSame(['total' => 4], $result['patients']);
        self::assertSame(['total' => 5], $result['appointments']);
        self::assertSame($doctors, $result['doctors']);
        self::assertSame(['total' => 25], $result['doctorsKpi']);
        self::assertSame(['B', 'A'], array_column($result['actsByType'], 'label'));
        self::assertSame('Doctor', $result['actes'][0]['medecin']);
        self::assertSame('', $result['actes'][0]['description']);
    }

    public function testReversedPeriodStillThrowsBeforeQuerying(): void
    {
        $repo = $this->createMock(TransactionRepository::class);
        $repo->expects(self::never())->method('findValidatedBetweenByTypes');
        $this->expectException(\InvalidArgumentException::class);
        $this->crossTables($repo)->getCrossTablePeriodOverview('2026-02-02', '2026-02-01');
    }

    public function testTypePolicyAndMapperPreserveFallbacksAndEditability(): void
    {
        $policy = new TransactionTypePolicy();
        self::assertSame('expense', $policy->resolveTransactionTypeKey(' dépense '));
        self::assertSame('revenue', $policy->resolveTransactionTypeKey('ENTRY'));
        self::assertSame('other', $policy->resolveTransactionTypeKey(null));
        self::assertSame('Revenue', $policy->normalizePersistedTransactionType('unknown'));
        self::assertSame(['Revenue'], $policy->resolveTransactionTypeAliases('other'));
        $mapper = new FinanceTransactionMapper($policy, new ManualTransactionPolicy());
        $transaction = $this->transaction('unknown', '50', '2026-02-01')->setSource('payroll');
        $row = $mapper->mapTransactionToArray($transaction);
        self::assertSame('unknown', $row['typeLabel']);
        self::assertFalse($row['isManual']);
        self::assertFalse($row['canEdit']);
        self::assertTrue($row['sourceManaged']);
        self::assertNull($row['lotFactureAssurance']);
        self::assertSame(['id' => null, 'libelle' => 'Cash', 'type' => 'cash'], $row['modeDePaiement']);
    }
}
