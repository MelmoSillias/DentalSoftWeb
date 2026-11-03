<?php

namespace App\Tests\Reporting;

use App\Billing\Entity\Assurance;
use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\LotFactureAssurance;
use App\Billing\Entity\ModeDePaiement;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\FactureAssuranceRepository;
use App\Billing\Repository\FactureCabinetRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Billing\Repository\ServiceCabinetRepository;
use App\Billing\Repository\TransactionRepository;
use App\Billing\Service\CabinetServiceBillingService;
use App\Billing\Service\InsuranceClaimService;
use App\Billing\Service\Workflow\InsuredInvoiceWorkflowService;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Repository\ActeMedicalRepository;
use App\CareDelivery\Repository\ConsultationRepository;
use App\ClinicalRecord\Repository\FicheMedicaleRepository;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\UserRepository;
use App\Inventory\Repository\ConsommableRepository;
use App\Patient\Entity\Patient;
use App\Patient\Entity\PatientAssuranceProfile;
use App\Patient\Repository\PatientRepository;
use App\Reporting\Service\ReportService;
use App\Scheduling\Repository\RdvRepository;
use App\Scheduling\Repository\SalleRepository;
use App\Settings\Service\GlobalSettingsService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ReportPrecisionTest extends TestCase
{
    private EntityManager $em;
    private ReportService $report;
    private CabinetServiceBillingService $billing;
    private ModeDePaiement $mode;
    private InsuranceClaimService $claims;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite is required');
        $config = ORMSetup::createAttributeMetadataConfiguration(glob(dirname(__DIR__, 2).'/src/*/Entity'), true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);
        $patients = new PatientRepository($registry);
        $modes = new ModeDePaiementRepository($registry);
        $invoices = new FactureAssuranceRepository($registry);
        $this->claims = new InsuranceClaimService($invoices, $modes, $this->em);
        $this->billing = new CabinetServiceBillingService($this->em, new ServiceCabinetRepository($registry), new FactureCabinetRepository($registry), $modes,
            $patients, $this->createMock(FicheMedicaleRepository::class), $this->claims, new InsuredInvoiceWorkflowService($this->claims, $invoices));
        $this->report = new ReportService($patients, new TransactionRepository($registry), new RdvRepository($registry), new EmployeRepository($registry),
            new SalleRepository($registry), new ConsommableRepository($registry), new UserRepository($registry), $this->em,
            new ActeMedicalRepository($registry), new ConsultationRepository($registry), new ArrayAdapter(), $this->createMock(GlobalSettingsService::class), $this->billing);
        $this->mode = (new ModeDePaiement())->setLibelle('Cash')->setType('cash');
        $this->em->persist($this->mode);
        $this->em->flush();
    }

    private function patient(string $registered = '2020-01-01'): Patient
    {
        $patient = (new Patient())->setNom('Test')->setPrenom('Patient')->setTelephone('123')
            ->setSexe('M')->setAdresse('Test')->setGroupeSanguin('O+')->setNumCarnet(uniqid('TEST'))
            ->setReferencement('Test')->setDateInscription(new \DateTime($registered));
        $this->em->persist($patient);
        $this->em->flush();
        return $patient;
    }

    private function transaction(string $type, float $amount, string $status = 'validated'): Transaction
    {
        $tx = (new Transaction())->setType($type)->setMontant((string) $amount)->setDateTransaction(new \DateTime('2026-11-02'))->setModeDePaiement($this->mode);
        if ($status === 'validated') $tx->markValidated(new \DateTimeImmutable('2026-11-02'));
        elseif ($status === 'cancelled') $tx->cancel('Test');
        else $tx->setValidationStatus($status);
        $this->em->persist($tx);
        return $tx;
    }

    public function testTreasuryAndTrendExcludeAllNonValidatedTransactions(): void
    {
        $this->transaction('Revenue', 10000);
        $this->transaction('Depense', 1000);
        $this->transaction('Depense', 9000, 'cancelled');
        $this->transaction('Revenue', 4000, 'rejected');
        $this->transaction('Revenue', 6000, 'pending');
        $this->transaction('Revenue', 2000)->setRolePaiement('insurance_lot');
        $this->em->flush();
        $stats = $this->report->globalStats('2026-11-01', '2026-11-03');
        self::assertEquals(12000, $stats['revenueTotal']);
        self::assertEquals(1000, $stats['expenseTotal']);
        self::assertEquals(11000, $stats['resultTotal']);
        self::assertEquals(2000, $stats['insuranceRefundsCollected']);
        self::assertEquals(12000, $this->report->computeCashRevenueForPeriod(new \DateTime('2026-11-01'), new \DateTime('2026-11-03')));
        self::assertEquals(11000, $this->report->buildRevenueTrend(new \DateTime('2026-11-01'), new \DateTime('2026-11-03'))[0]['amount']);
    }

    public function testUniquePatientsRegistrationsAndRepeatVisitsAreSeparated(): void
    {
        $old = $this->patient();
        $new = $this->patient('2026-11-02');
        $this->patient('2026-11-02'); // Registered but not seen.
        $consultations = [];
        foreach ([$old, $old, $new] as $patient) {
            $c = (new Consultation())->setPatient($patient)->setCreatedAt(new \DateTime('2026-11-02'));
            $consultations[] = $c;
            $this->em->persist($c);
        }
        $this->em->flush();
        $stats = $this->report->periodicPatients(new \DateTime('2026-11-01'), new \DateTime('2026-11-03'));
        self::assertSame(2, $stats['newPatients']);
        self::assertSame(2, $stats['uniquePatientsSeen']);
        self::assertSame(1, $stats['newPatientsSeen']);
        self::assertSame(1, $stats['returningPatients']);
        self::assertSame(1, $stats['repeatConsultations']);
        $allTime = $this->report->periodicPatients(null, null);
        self::assertSame(2, $allTime['newPatientsSeen']);
        self::assertSame(0, $allTime['returningPatients']);
        $doctor = (new Employe())->setTypeSalaire('non_defini');
        $doctorStats = (new \ReflectionMethod(ReportService::class, 'computeDoctorStats'))->invoke($this->report, $doctor, $consultations,
            new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-11-03'), []);
        self::assertSame(2, $doctorStats['unique_patients']);
        self::assertSame(1, $doctorStats['new_patients']);
        self::assertSame(1, $doctorStats['returning_patients']);
        self::assertSame(1, $doctorStats['repeat_consultations']);
    }

    public function testCabinetPeriodBasisAndPartialInsurerReceivables(): void
    {
        $patient = $this->patient();
        $assurance = (new Assurance())->setCode('TEST')->setNom('Test');
        $patient->setAssuranceProfile((new PatientAssuranceProfile())->setAssurance($assurance)->setCoverageRate(80));
        $this->em->persist($assurance);
        $this->em->flush();
        $create = fn (string $date) => $this->billing->createForPatient($patient->getId(), ['designation' => 'Radio', 'prix' => 10000, 'date' => $date, 'coveredByInsurance' => true])['data'];
        $old = $create('2026-10-01');
        $current = $create('2026-11-02');
        $cancelled = $create('2026-11-02');
        $this->billing->cancel($cancelled['id']);
        $this->claims->payPatientShare($old['facture']['id'], $this->mode->getId(), 500, new \DateTimeImmutable('2026-11-03'));
        $this->claims->payPatientShare($current['facture']['id'], $this->mode->getId(), 500, new \DateTimeImmutable('2026-11-03'));
        $this->claims->payPatientShare($current['facture']['id'], $this->mode->getId(), 1500, new \DateTimeImmutable('2026-11-20'));
        $stats = $this->billing->summarizePeriod(new \DateTime('2026-11-01'), new \DateTime('2026-11-03 23:59:59'));
        self::assertEquals(10000, $stats['facture']);
        self::assertEquals(2000, $stats['partPatient']);
        self::assertEquals(8000, $stats['partAssurance']);
        self::assertEquals(1000, $stats['encaisse']);
        self::assertEquals(500, $stats['encaissePrestationsPeriode']);
        self::assertEquals(500, $stats['encaisseHorsPeriode']);
        self::assertEquals(1500, $stats['reste']);
        self::assertEquals(0, $stats['resteActuel']);
        $lot = (new LotFactureAssurance())->setAssurance($assurance)->setDateDebut(new \DateTime('2026-10-01'))->setDateFin(new \DateTime('2026-11-30'));
        foreach ([$old, $current] as $row) $lot->addFactureAssurance($this->em->find(FactureAssurance::class, $row['facture']['id']));
        $this->em->persist($lot);
        $this->transaction('Revenue', 3000)->setRolePaiement('insurance_lot')->setLotFactureAssurance($lot);
        $this->transaction('Revenue', 1000, 'rejected')->setRolePaiement('insurance_lot')->setLotFactureAssurance($lot);
        $this->em->flush();
        $this->em->refresh($lot);
        self::assertEquals(13000, (new \ReflectionMethod(ReportService::class, 'insuranceOutstandingCurrent'))->invoke($this->report));
    }

    public function testDoctorInsuranceRemainsRemuneratedBeforeRefund(): void
    {
        $doctor = (new Employe())->setTypeSalaire('pourcentage')->setValeurSalaire(30);
        $patient = $this->patient();
        $c = (new Consultation())->setPatient($patient)->setCreatedAt(new \DateTime('2026-11-02'))->setStatut(1);
        $invoice = (new FactureAssurance())->setConsultation($c)->setCoverageRate(80)->setIsConsultationPayante(true)->setConsultationAmount(10000);
        $c->setFactureAssurance($invoice);
        $stats = (new \ReflectionMethod(ReportService::class, 'computeDoctorStats'))->invoke($this->report, $doctor, [$c],
            new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-11-03'), []);
        self::assertEquals(8000, $stats['revenue_assurance']);
        self::assertEquals(2400, $stats['salary']);
        self::assertEquals(0, $stats['revenue_cash']);
        self::assertFalse($invoice->isRecouvre());
    }

    public function testFinanceMonthlyTotalsUseTheSameValidationRule(): void
    {
        $transactions = [
            $this->transaction('Revenue', 10000), $this->transaction('Depense', 1000),
            $this->transaction('Revenue', 6000, 'pending'), $this->transaction('Revenue', 4000, 'rejected'),
            $this->transaction('Depense', 9000, 'cancelled'),
        ];
        $finance = (new \ReflectionClass(\App\Billing\Service\FinanceService::class))->newInstanceWithoutConstructor();
        $stats = $finance->computeMonthlySummary($transactions);
        self::assertEquals(10000, $stats['monthlyRevenues'][10]);
        self::assertEquals(1000, $stats['monthlyExpenses'][10]);
        self::assertEquals(9000, $stats['monthlyProfits'][10]);
    }

    public function testReceptionSeparatesScheduledPendingConfirmedAndCancelledAppointments(): void
    {
        $patient = $this->patient();
        $this->em->persist((new Consultation())->setPatient($patient)->setCreatedAt(new \DateTime('2026-11-02')));
        foreach ([0, 1, -2] as $status) {
            $rdv = (new \App\Scheduling\Entity\Rdv())->setPatient($patient)->setDateCreation(new \DateTime('2026-11-01'))
                ->setDateRdv(new \DateTime('2026-11-02'))->setStatut($status)->setDescription('Test')->setDuration(30);
            $this->em->persist($rdv);
        }
        $this->em->flush();
        $stats = $this->report->getReceptionStats(new \DateTimeImmutable('2026-11-02'), new \DateTimeImmutable('2026-11-02 23:59:59'));
        self::assertSame(3, $stats['totalAppointments']);
        self::assertSame(1, $stats['pendingAppointments']);
        self::assertSame(1, $stats['confirmedAppointments']);
        self::assertSame(1, $stats['cancelledAppointments']);
        self::assertSame(0, $stats['paidConsultations']);
        self::assertSame(1, $stats['freeConsultations']);
    }
}
