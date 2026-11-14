<?php

namespace App\Tests\Billing;

use App\Billing\Application\Handler\FinanceAccountQueryHandler;
use App\Billing\Application\Handler\FinanceCrossTableQueryHandler;
use App\Billing\Application\Handler\FinanceTransactionQueryHandler;
use App\Billing\Application\Mapper\FinanceTransactionMapper;
use App\Billing\Application\Policy\ManualTransactionPolicy;
use App\Billing\Application\Policy\TransactionTypePolicy;
use App\Billing\Application\Service\FinanceService;
use App\Billing\Application\Service\LotFactureAssuranceService;
use App\Billing\Application\UseCase\ManageFinanceTransactions;
use App\Billing\Domain\Entity\Facture;
use App\Billing\Domain\Entity\FactureAssurance;
use App\Billing\Domain\Entity\LotFactureAssurance;
use App\Billing\Domain\Entity\ModeDePaiement;
use App\Billing\Domain\Entity\Paiement;
use App\Billing\Domain\Entity\Transaction;
use App\Billing\Infrastructure\Persistence\Doctrine\ChargeFixeRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\ModeDePaiementRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\TransactionRepository;
use App\CareDelivery\Domain\Entity\Consultation;
use App\Reporting\Application\Service\ReportService;
use App\Settings\Application\Service\GlobalSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FinanceServiceRegressionTest extends TestCase
{
    private function transaction(string $type = 'Revenue', string $amount = '100', string $date = '2026-02-01'): Transaction
    {
        return (new Transaction())->setType($type)->setMontant($amount)
            ->setDateTransaction(new \DateTimeImmutable($date))
            ->setModeDePaiement((new ModeDePaiement())->setLibelle('Cash')->setType('cash'));
    }

    private function facade(
        TransactionRepository $transactions,
        ModeDePaiementRepository $modes,
        EntityManagerInterface $manager,
        ?LotFactureAssuranceService $lots = null,
    ): FinanceService {
        $typePolicy = new TransactionTypePolicy();
        $manualPolicy = new ManualTransactionPolicy();
        $mapper = new FinanceTransactionMapper($typePolicy, $manualPolicy);

        return new FinanceService(
            $this->createMock(ChargeFixeRepository::class),
            $modes,
            $manager,
            new FinanceAccountQueryHandler($modes, $transactions),
            $this->createMock(FinanceCrossTableQueryHandler::class),
            new FinanceTransactionQueryHandler($transactions, $mapper),
            new ManageFinanceTransactions($modes, $transactions, $manager,
                $lots ?? $this->createMock(LotFactureAssuranceService::class), $mapper, $typePolicy, $manualPolicy),
        );
    }

    public function testCreateAndTransferPreserveTypesDatesAndSingleFlush(): void
    {
        $from = (new ModeDePaiement())->setLibelle('Cash');
        $to = (new ModeDePaiement())->setLibelle('Bank');
        $modes = $this->createMock(ModeDePaiementRepository::class);
        $modes->method('find')->willReturnMap([[1, null, null, $from], [2, null, null, $to]]);
        $manager = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $manager->expects(self::exactly(3))->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $manager->expects(self::exactly(2))->method('flush');
        $service = $this->facade($this->createMock(TransactionRepository::class), $modes, $manager);
        $date = new \DateTime('2026-02-04 12:34:56');
        self::assertSame(['success' => true, 'transactionId' => null], $service->createTransaction('expense', 125.5, 'Manual', $date, 1, 'Rent'));
        self::assertSame('Depense', $persisted[0]->getType());
        self::assertSame('Rent', $persisted[0]->getMotif());
        self::assertSame($date, $persisted[0]->getDateTransaction());
        self::assertEquals(\DateTimeImmutable::createFromMutable($date), $persisted[0]->getValidatedAt());
        $service->transferInterCompte(1, 2, 80, 'Deposit', $date);
        self::assertSame(['Depense', 'Revenue'], [$persisted[1]->getType(), $persisted[2]->getType()]);
        self::assertSame($from, $persisted[1]->getModeDePaiement());
        self::assertSame($to, $persisted[2]->getModeDePaiement());
        self::assertSame($persisted[1]->getValidatedAt(), $persisted[2]->getValidatedAt());
        self::assertSame('[Transfert] vers Bank - Deposit', $persisted[1]->getDescription());
        self::assertSame('[Transfert] depuis Cash - Deposit', $persisted[2]->getDescription());
        self::assertSame($persisted[1]->getMontant(), $persisted[2]->getMontant());
        self::assertTrue($persisted[2]->isValidated());
        self::assertSame(['error' => 'Comptes invalides'], $service->transferInterCompte(1, 1, 80, '', $date));
    }

    /** @dataProvider validationStates */
    public function testManualUpdateKeepsValidationStateAndMappedContract(bool $validated): void
    {
        $transaction = $this->transaction()->setValidated($validated);
        $repo = $this->createMock(TransactionRepository::class);
        $repo->method('find')->willReturn($transaction);
        $modes = $this->createMock(ModeDePaiementRepository::class);
        $modes->method('find')->willReturn($transaction->getModeDePaiement());
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('flush');
        $manager->expects(self::never())->method('persist');
        $date = new \DateTimeImmutable('2026-03-02 10:00:00');
        $result = $this->facade($repo, $modes, $manager)->updateTransaction(1, 'unknown', 55, null, $date, 1, 'Correction');
        self::assertTrue($result['success']);
        self::assertSame('Revenue', $transaction->getType());
        self::assertSame($validated, $transaction->isValidated());
        self::assertSame($validated ? $date : null, $transaction->getValidatedAt());
        self::assertSame('2026-03-02 10:00:00', $result['transaction']['dateTransaction']);
        self::assertSame('revenue', $result['transaction']['typeKey']);
        self::assertSame('Revenu', $result['transaction']['typeLabel']);
        self::assertSame('55', $result['transaction']['amount']);
        self::assertTrue($result['transaction']['canEdit']);
        self::assertSame('Correction', $result['transaction']['motif']);
    }

    public function validationStates(): array { return [[false], [true]]; }

    /** @dataProvider removalActions */
    public function testRejectionAndDeletionDetachPaymentBeforeLotRollback(string $action): void
    {
        $invoice = new Facture();
        $consultation = new Consultation();
        $payment = (new Paiement())->setFacture($invoice)->setConsultation($consultation)->setFactureAssurance(new FactureAssurance());
        $invoice->addPaiement($payment);
        $consultation->setPaiement($payment);
        $lot = new LotFactureAssurance();
        $transaction = $this->transaction()->setPaiement($payment)->setConsultation($consultation)->setLotFactureAssurance($lot)->markValidated();
        $repo = $this->createMock(TransactionRepository::class);
        $repo->method('find')->willReturn($transaction);
        $manager = $this->createMock(EntityManagerInterface::class);
        $removed = [];
        $manager->expects(self::exactly($action === 'delete' ? 2 : 1))->method('remove')->willReturnCallback(function ($entity) use (&$removed) {
            $removed[] = $entity;
        });
        $manager->expects(self::once())->method('flush');
        $lots = $this->createMock(LotFactureAssuranceService::class);
        $lots->expects(self::once())->method('rollbackLotRecoveryFromTransaction')->with($transaction)->willReturnCallback(function ($current) use ($lot, $payment, &$removed) {
            self::assertNull($current->getPaiement());
            self::assertSame($lot, $current->getLotFactureAssurance());
            self::assertSame([$payment], $removed);
        });
        $service = $this->facade($repo, $this->createMock(ModeDePaiementRepository::class), $manager, $lots);
        $result = $action === 'delete' ? $service->deleteTransaction(1) : $service->updateTransactionValidationStatus(1, 'rejected', 'Duplicate');
        self::assertSame(['success' => true], $result);
        self::assertNull($consultation->getPaiement());
        self::assertCount(0, $invoice->getPaiements());
        self::assertNull($payment->getFacture());
        self::assertNull($payment->getConsultation());
        self::assertNull($payment->getFactureAssurance());
        if ($action === 'delete') {
            self::assertSame([$payment, $transaction], $removed);
            self::assertNull($transaction->getConsultation());
            self::assertNull($transaction->getLotFactureAssurance());
        } else {
            self::assertSame('rejected', $transaction->getValidationStatus());
            self::assertSame('Duplicate', $transaction->getValidationComment());
            self::assertNull($transaction->getValidatedAt());
            self::assertSame($consultation, $transaction->getConsultation());
        }
    }

    public function removalActions(): array { return [['delete'], ['reject']]; }

    public function testValidationAndInvalidStatusDoNotDetachPayment(): void
    {
        $payment = new Paiement();
        $transaction = $this->transaction()->setPaiement($payment)->setValidationComment('Old');
        $repo = $this->createMock(TransactionRepository::class);
        $repo->method('find')->willReturn($transaction);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('remove');
        $manager->expects(self::once())->method('flush');
        $lots = $this->createMock(LotFactureAssuranceService::class);
        $lots->expects(self::never())->method('rollbackLotRecoveryFromTransaction');
        $service = $this->facade($repo, $this->createMock(ModeDePaiementRepository::class), $manager, $lots);
        self::assertSame(400, $service->updateTransactionValidationStatus(1, 'invalid')['status']);
        $date = new \DateTimeImmutable('2026-01-01');
        self::assertSame(['success' => true], $service->updateTransactionValidationStatus(1, 'validated', 'Ignored', $date));
        self::assertSame($date, $transaction->getValidatedAt());
        self::assertNull($transaction->getValidationComment());
        self::assertSame($payment, $transaction->getPaiement());
        self::assertSame(400, $service->updateTransaction(1, 'Revenue', 50, null, $date, 1)['status']);
    }

    public function testExtractedServicesAutowireWithoutCyclesAndFacadeUsesInjectedHandler(): void
    {
        $container = new ContainerBuilder();
        foreach ([ChargeFixeRepository::class, ModeDePaiementRepository::class, TransactionRepository::class,
            EntityManagerInterface::class, GlobalSettingsService::class, LotFactureAssuranceService::class, ReportService::class] as $dependency) {
            $container->register($dependency, $dependency)->setSynthetic(true)->setPublic(true);
        }
        foreach ([FinanceService::class, FinanceAccountQueryHandler::class, FinanceCrossTableQueryHandler::class,
            FinanceTransactionQueryHandler::class, ManageFinanceTransactions::class, FinanceTransactionMapper::class,
            TransactionTypePolicy::class, ManualTransactionPolicy::class] as $service) {
            $container->register($service, $service)->setAutowired(true)->setPublic(true);
        }
        $container->compile();
        foreach ([ChargeFixeRepository::class, ModeDePaiementRepository::class, TransactionRepository::class,
            EntityManagerInterface::class, GlobalSettingsService::class, LotFactureAssuranceService::class, ReportService::class] as $dependency) {
            $container->set($dependency, $this->createMock($dependency));
        }
        $facade = $container->get(FinanceService::class);
        self::assertSame($container->get(FinanceAccountQueryHandler::class), (new \ReflectionProperty($facade, 'accountQueries'))->getValue($facade));
        self::assertSame($container->get(FinanceCrossTableQueryHandler::class), (new \ReflectionProperty($facade, 'crossTableQueries'))->getValue($facade));
        self::assertSame($container->get(FinanceTransactionQueryHandler::class), (new \ReflectionProperty($facade, 'transactionQueries'))->getValue($facade));
        self::assertSame($container->get(ManageFinanceTransactions::class), (new \ReflectionProperty($facade, 'transactionUseCase'))->getValue($facade));
        self::assertSame([(int) date('Y')], $facade->getAvailableTransactionYears());
    }
}
