<?php

namespace App\Tests\IdentityAccess\Service;

use App\Billing\Entity\ModeDePaiement;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\ModeDePaiementRepository;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\InpsQuarter;
use App\IdentityAccess\Entity\InpsRemittance;
use App\IdentityAccess\Entity\SalaryPayment;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\SalaryPaymentRepository;
use App\IdentityAccess\Service\InpsService;
use App\IdentityAccess\Service\PayrollService;
use App\Reporting\Service\ReportService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class InpsIntegrityTest extends TestCase
{
    private EntityManager $em;
    private PayrollService $payroll;
    private InpsService $inps;
    private Employe $employee;
    private ModeDePaiement $mode;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3) . '/src';
        $paths = glob($root . '/*/Entity');
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
        $config->setNamingStrategy(new \Doctrine\ORM\Mapping\UnderscoreNamingStrategy(CASE_LOWER, true));
        $registry = $this->createMock(ManagerRegistry::class);
        $config->setRepositoryFactory(new class($registry) implements RepositoryFactory {
            private array $repositories = [];
            public function __construct(private ManagerRegistry $registry) {}
            public function getRepository(EntityManagerInterface $em, string $entityName): EntityRepository
            {
                $metadata = $em->getClassMetadata($entityName);
                $class = $metadata->customRepositoryClassName;
                return $this->repositories[$entityName] ??= $class ? new $class($this->registry) : new EntityRepository($em, $metadata);
            }
        });
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        $registry->method('getManagerForClass')->willReturn($this->em);
        (new SchemaTool($this->em))->createSchema(array_map(fn ($class) => $this->em->getClassMetadata($class), [Employe::class, ModeDePaiement::class, Transaction::class, SalaryPayment::class, InpsQuarter::class, InpsRemittance::class]));
        $this->employee = (new Employe())->setNom('Test')->setPrenom('INPS')->setFonction('Assistant')->setType('Autre')
            ->setDateEmbauche(new \DateTimeImmutable('2020-01-01'))->setTypeContrat('CDI')->setTypeSalaire('fixe')->setValeurSalaire(100000)->setTypePrime('fixe')->setValeurPrime(0);
        $this->mode = (new ModeDePaiement())->setLibelle('Espèces')->setType('cash');
        foreach ([$this->employee, $this->mode, new InpsQuarter(20261)] as $entity) $this->em->persist($entity);
        $this->em->flush();
        $this->inps = new InpsService($this->em, $this->em->getRepository(ModeDePaiement::class), $this->createMock(Security::class));
        $this->payroll = new PayrollService($this->em, $this->em->getRepository(Employe::class), $this->em->getRepository(SalaryPayment::class), $this->em->getRepository(ModeDePaiement::class), $this->createMock(ReportService::class), $this->inps);
    }

    private function pay(float $amount = 30000, float $inps = 10000): array
    {
        return $this->payroll->createSalaryPayment(['employeeId' => $this->employee->getId(), 'year' => 2026, 'month' => 3,
            'paidAmount' => $amount, 'inps' => $inps, 'paymentMethodId' => $this->mode->getId(), 'paidAt' => '2026-03-05', 'requestId' => bin2hex(random_bytes(16)),
            'periodFingerprint' => $this->payroll->getPaymentContext($this->employee->getId(), 3, 2026)['periodFingerprint']])['payment'];
    }

    private function remit(float $amount = 10000, array $extra = []): array
    {
        return $this->inps->remit([...$extra, 'year' => 2026, 'quarter' => 1, 'amount' => $amount, 'beneficiary' => 'Direction',
            'paymentMethodId' => $this->mode->getId(), 'paidAt' => '2026-04-01', 'fingerprint' => $this->inps->overview(2026, 1)['fingerprint'], 'requestId' => bin2hex(random_bytes(16))]);
    }

    public function testPartialSalaryPaymentsProduceOnlyOneMonthlyRetention(): void
    {
        $this->pay();
        $this->pay(20000, 500);
        $state = $this->inps->overview(2026, 1);
        self::assertSame(10000.0, $state['due']);
        self::assertSame(10000.0, $state['employees'][0]['total']);
        self::assertSame(50000.0, $this->payroll->getPaymentContext($this->employee->getId(), 3, 2026)['breakdown']['alreadyPaid']);
    }

    public function testCorrectionPreservesRemittanceAndCreatesSurplus(): void
    {
        $payment = $this->pay();
        $state = $this->remit();
        $updated = $this->payroll->updateSalaryPayment($payment['id'], ['version' => $payment['version'], 'reason' => 'Retenue corrigée', 'inps' => 7000, 'inpsFingerprint' => $state['fingerprint']]);
        $state = $this->inps->overview(2026, 1);
        self::assertSame(7000.0, $state['due']);
        self::assertSame(10000.0, $state['remitted']);
        self::assertSame(3000.0, $state['surplus']);
        self::assertSame('validated', $state['remittances'][0]['status']);
        self::assertGreaterThan($payment['version'], $updated['payment']['version']);
    }

    public function testChangingPaymentKeepsCancelledTransactionAndCreatesReplacement(): void
    {
        $payment = $this->pay();
        $before = $this->em->getRepository(SalaryPayment::class)->find($payment['id'])->getTransaction();
        $this->payroll->updateSalaryPayment($payment['id'], ['version' => $payment['version'], 'reason' => 'Erreur de montant', 'paidAmount' => 25000]);
        $transactions = $this->em->getRepository(Transaction::class)->findAll();
        self::assertCount(2, $transactions);
        self::assertSame('cancelled', $before->getValidationStatus());
        self::assertSame('30000', $before->getMontant());
        self::assertCount(2, $this->payroll->getSalaryPayment($payment['id'])['history']);
    }

    public function testSolePaymentCancellationPreservesBothAuditTrails(): void
    {
        $payment = $this->pay();
        $this->payroll->deleteSalaryPayment($payment['id'], ['version' => $payment['version'], 'reason' => 'Saisie en double', 'entryErrorConfirmed' => true]);
        self::assertSame(0.0, $this->inps->overview(2026, 1)['due']);
        self::assertSame('cancelled', $this->payroll->getSalaryPayment($payment['id'])['status']);
        self::assertSame('cancelled', $this->em->getRepository(Transaction::class)->findAll()[0]->getValidationStatus());
        self::assertSame(0.0, $this->em->getRepository(SalaryPayment::class)->sumPaidForMonthlyPeriod($this->employee, 3, 2026));
    }

    public function testFirstCannotBeCancelledWhileOtherPaymentsExist(): void
    {
        $payment = $this->pay();
        $this->pay(20000);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('autres paiements');
        $this->payroll->deleteSalaryPayment($payment['id'], ['version' => $payment['version'], 'reason' => 'Erreur', 'entryErrorConfirmed' => true]);
    }

    public function testOverpaymentIsRejectedOnEdit(): void
    {
        $first = $this->pay();
        $this->pay(50000);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('depasse le reste autorise');
        $this->payroll->updateSalaryPayment($first['id'], ['version' => $first['version'], 'reason' => 'Erreur', 'paidAmount' => 40000.01]);
    }

    public function testRetentionCorrectionRequiresCurrentExplicitConfirmation(): void
    {
        $payment = $this->pay();
        $this->remit();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('confirmez la régularisation');
        $this->payroll->updateSalaryPayment($payment['id'], ['version' => $payment['version'], 'reason' => 'Erreur', 'inps' => 5000]);
    }

    public function testStaleVersionIsRejected(): void
    {
        $payment = $this->pay();
        $this->payroll->updateSalaryPayment($payment['id'], ['version' => $payment['version'], 'reason' => 'Note', 'note' => 'Correction']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('paiement a changé');
        $this->payroll->updateSalaryPayment($payment['id'], ['version' => $payment['version'], 'reason' => 'Ancien écran', 'paidAmount' => 20000]);
    }

    public function testExistingExpenseCanBeLinkedWithoutCreatingAnotherExpense(): void
    {
        $this->pay();
        $transaction = (new Transaction())->setMontant('10000')->setType('Depense')->setModeDePaiement($this->mode)->setDateTransaction(new \DateTimeImmutable('2026-04-01'))->markValidated();
        $this->em->persist($transaction);
        $this->em->flush();
        $state = $this->remit(10000, ['existingTransactionId' => $transaction->getId()]);
        self::assertSame(10000.0, $state['remitted']);
        self::assertCount(2, $this->em->getRepository(Transaction::class)->findAll());
        self::assertTrue($transaction->isSourceManaged());
    }

    public function testClosedQuarterRejectsNewPayments(): void
    {
        $this->pay();
        $this->remit();
        $this->inps->setClosed(2026, 1, ['closed' => true, 'reason' => 'Contrôlé', 'fingerprint' => $this->inps->overview(2026, 1)['fingerprint']]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('trimestre est clôturé');
        $this->pay(20000);
    }

    public function testCalculationIsLockedAsSoonAsAnotherPaymentExists(): void
    {
        $first = $this->pay();
        $this->pay(20000);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('calcul mensuel est fige');
        $this->payroll->updateSalaryPayment($first['id'], ['version' => $first['version'], 'reason' => 'Correction', 'inps' => 5000]);
    }

    public function testIdenticalCreateRequestDoesNotPayTwice(): void
    {
        $payload = ['employeeId' => $this->employee->getId(), 'year' => 2026, 'month' => 3, 'paidAmount' => 30000,
            'inps' => 10000, 'paymentMethodId' => $this->mode->getId(), 'requestId' => 'same-request-12345678',
            'periodFingerprint' => $this->payroll->getPaymentContext($this->employee->getId(), 3, 2026)['periodFingerprint']];
        $first = $this->payroll->createSalaryPayment($payload);
        $second = $this->payroll->createSalaryPayment($payload);
        self::assertSame($first['payment']['id'], $second['payment']['id']);
        self::assertCount(1, $this->em->getRepository(Transaction::class)->findAll());
    }

    public function testRemittanceRetryDoesNotCreateAnotherExpense(): void
    {
        $this->pay();
        $payload = ['year' => 2026, 'quarter' => 1, 'amount' => 10000, 'beneficiary' => 'Direction', 'paymentMethodId' => $this->mode->getId(),
            'paidAt' => '2026-04-01', 'requestId' => 'same-remittance-123456', 'fingerprint' => $this->inps->overview(2026, 1)['fingerprint']];
        $this->inps->remit($payload);
        $state = $this->inps->remit($payload);
        self::assertSame(10000.0, $state['remitted']);
        self::assertCount(1, $state['remittances']);
        self::assertCount(2, $this->em->getRepository(Transaction::class)->findAll());
    }

    public function testCancellingErroneousRemittanceKeepsHistoryAndRestoresAmountDue(): void
    {
        $this->pay();
        $state = $this->remit();
        $state = $this->inps->cancel($state['remittances'][0]['id'], ['fingerprint' => $state['fingerprint'], 'reason' => 'Aucun versement effectué', 'entryErrorConfirmed' => true]);
        self::assertSame(0.0, $state['remitted']);
        self::assertSame(10000.0, $state['remaining']);
        self::assertSame('cancelled', $state['remittances'][0]['status']);
        self::assertCount(2, $state['remittances'][0]['audit']);
        self::assertCount(2, $this->em->getRepository(Transaction::class)->findAll());
    }

    public function testCancellingLastPaymentWithRemittanceRetainsActualMoneyMovement(): void
    {
        $payment = $this->pay();
        $state = $this->remit();
        $this->payroll->deleteSalaryPayment($payment['id'], ['version' => $payment['version'], 'reason' => 'Salaire saisi par erreur', 'entryErrorConfirmed' => true, 'inpsFingerprint' => $state['fingerprint']]);
        $state = $this->inps->overview(2026, 1);
        self::assertSame(0.0, $state['due']);
        self::assertSame(10000.0, $state['surplus']);
        self::assertSame('validated', $state['remittances'][0]['status']);
    }

    public function testHistoricalCalculationSurvivesFrequencyChange(): void
    {
        $this->pay();
        $this->employee->setFrequencePaiement('journalier');
        $this->em->flush();
        $context = $this->payroll->getPaymentContext($this->employee->getId(), 3, 2026);
        self::assertSame('mensuel', $context['frequencePaiement']);
        self::assertSame(90000.0, $context['calculatedAmount']);
    }

    public function testStaleCreateFormCannotUseAnOutdatedBalance(): void
    {
        $context = $this->payroll->getPaymentContext($this->employee->getId(), 3, 2026);
        $this->pay();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('paie de cette période a changé');
        $this->payroll->createSalaryPayment(['employeeId' => $this->employee->getId(), 'year' => 2026, 'month' => 3,
            'paidAmount' => 10000, 'paymentMethodId' => $this->mode->getId(), 'requestId' => 'old-create-form-12345', 'periodFingerprint' => $context['periodFingerprint']]);
    }

    public function testClosureCanBeReopenedWithAnAuditReason(): void
    {
        $this->pay();
        $this->remit();
        $state = $this->inps->setClosed(2026, 1, ['closed' => true, 'reason' => 'Rapprochement', 'fingerprint' => $this->inps->overview(2026, 1)['fingerprint']]);
        $state = $this->inps->setClosed(2026, 1, ['closed' => false, 'reason' => 'Correction justifiée', 'fingerprint' => $state['fingerprint']]);
        self::assertFalse($state['closed']);
        self::assertCount(2, $state['audit']);
        self::assertSame('reopened', $state['audit'][1]['action']);
    }
}
