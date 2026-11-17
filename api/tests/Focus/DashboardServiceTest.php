<?php

declare(strict_types=1);

namespace App\Tests\Focus;

use App\Billing\Domain\Entity\Assurance;
use App\Billing\Domain\Entity\Facture;
use App\Billing\Domain\Entity\FactureAssurance;
use App\Billing\Domain\Entity\ModeDePaiement;
use App\Billing\Domain\Entity\Paiement;
use App\Billing\Domain\Entity\Transaction;
use App\CareDelivery\Domain\Entity\ActeMedical;
use App\CareDelivery\Domain\Entity\Consultation;
use App\Focus\Application\Port\DashboardClockInterface;
use App\Focus\Application\Port\DashboardReportingInterface;
use App\Focus\Application\Service\DashboardService;
use App\Focus\Infrastructure\Cache\SymfonyDashboardCache;
use App\Focus\Infrastructure\Persistence\Doctrine\DoctrineDashboardQueries;
use App\IdentityAccess\Domain\Entity\Employe;
use App\Patient\Domain\Entity\Patient;
use App\Scheduling\Domain\Entity\Rdv;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class DashboardServiceTest extends TestCase
{
    private EntityManager $em;
    private DashboardService $dashboard;
    private DoctrineDashboardQueries $queries;
    private DashboardReportingInterface $reports;
    private Employe $doctorA;
    private Employe $doctorB;
    private \DateTimeImmutable $from;
    private \DateTimeImmutable $to;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite required.');
        $config = ORMSetup::createAttributeMetadataConfiguration(glob(dirname(__DIR__,2).'/src/*/Domain/Entity'),true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER,true));
        $this->em = new EntityManager(DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]),$config);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->from = new \DateTimeImmutable('2026-11-02 00:00:00');
        $this->to = new \DateTimeImmutable('2026-11-02 23:59:59');
        $this->doctorA = $this->doctor('Alpha');
        $this->doctorB = $this->doctor('Beta');
        $patientA = $this->patient('Patient Alpha');
        $patientB = $this->patient('Patient Beta');
        $deleted = $this->patient('Deleted');
        $deleted->setDeletedAt(new \DateTimeImmutable('2026-11-02 08:00:00'));
        $pendingA = $this->consultation($patientA,$this->doctorA,'09:00:00');
        $paidA = $this->consultation($patientA,$this->doctorA,'10:00:00')->setStatut(1);
        $pendingB = $this->consultation($patientB,$this->doctorB,'11:00:00');
        $this->consultation($deleted,$this->doctorA,'09:00:00');
        $mode = (new ModeDePaiement())->setLibelle('Cash')->setType('cash');
        $this->em->persist($mode);
        $invoiceA = $this->invoice($pendingA,'Cleaning',100.0,2);
        $this->invoice($pendingB,'Extraction',80.0,1);
        $partial = (new Paiement())->setMode($mode)->setMontant(50.0)->setDate(new \DateTime('2026-11-02 10:00:00'));
        $invoiceA->addPaiement($partial);
        $ticket = (new Paiement())->setMode($mode)->setMontant(30.0)->setDate(new \DateTime('2026-11-02 10:00:00'))->setConsultation($paidA);
        $paidA->setPaiement($ticket);
        foreach ([$partial,$ticket] as $payment) $this->em->persist($payment);
        foreach ([['Revenue',100.0],['Expense',25.0]] as [$type,$amount]) {
            $transaction = (new Transaction())->setType($type)->setMontant((string)$amount)->setModeDePaiement($mode)->setDateTransaction(new \DateTime('2026-11-02 10:00:00'));
            $this->em->persist($transaction);
        }
        $this->appointment($patientA,$this->doctorA,0,'09:30:00');
        $this->appointment($patientA,$this->doctorA,-2,'10:30:00');
        $this->appointment($patientB,$this->doctorB,0,'11:30:00');
        $this->appointment($deleted,$this->doctorA,0,'09:00:00');
        $this->em->flush();
        $doctorAId = $this->doctorA->getId(); $doctorBId = $this->doctorB->getId();
        $this->em->clear();
        $this->doctorA = $this->em->find(Employe::class,$doctorAId);
        $this->doctorB = $this->em->find(Employe::class,$doctorBId);
        $this->queries = new DoctrineDashboardQueries($this->em);
        $this->reports = $this->createMock(DashboardReportingInterface::class);
        $this->reports->method('cashRevenue')->willReturn(75.0);
        $this->reports->method('doctorReports')->willReturn(['kpi'=>['total'=>3],'doctors'=>[['name'=>'Alpha']]]);
        $clock = $this->createMock(DashboardClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-11-02 12:00:00'));
        $this->dashboard = new DashboardService($this->queries,$this->reports,new SymfonyDashboardCache(new ArrayAdapter()),$clock);
    }

    protected function tearDown(): void { if (isset($this->em)) $this->em->getConnection()->close(); }

    public function testCardsKeepClinicalAndFinancialResultsAndExcludeDeletedPatients(): void
    {
        $admin = $this->dashboard->getAdminCards($this->from,$this->to);
        self::assertSame(['new'=>2,'total'=>2],$admin['patients']);
        self::assertSame(['total'=>3,'paid'=>1,'free'=>2],$admin['consultations']);
        self::assertSame(['pending'=>2,'cancelled'=>1],$admin['appointments']);
        self::assertSame(['total'=>75.0,'unpaid'=>230.0],$admin['cash']);
        self::assertSame(['total'=>2,'avgWaitMinutes'=>120],$admin['pendingConsultations']);
        self::assertSame($admin,$this->dashboard->getReceptionCards($this->from,$this->to));
        $doctor = $this->dashboard->getMedecinCards($this->doctorA,$this->from,$this->to);
        self::assertSame(['total'=>2,'paid'=>1,'free'=>1],$doctor['consultations']);
        self::assertSame(['total'=>200.0,'unpaid'=>150.0],$doctor['revenue']);
        self::assertSame(['total'=>1,'avgWaitMinutes'=>180],$doctor['pendingConsultations']);
        self::assertSame(1,$doctor['patients']['total']);
        self::assertSame(1,$this->dashboard->getMedecinCards($this->doctorB,$this->from,$this->to)['consultations']['total']);
    }

    public function testAllRoleProjectionsRetainTheirPayloadsAndDoctorScope(): void
    {
        $admin = $this->dashboard->getAdminCarousels($this->from,$this->to);
        self::assertSame(2,$admin['doctorConsultations'][0]['total']);
        self::assertSame(50.0,$admin['doctorConsultations'][0]['paidRate']);
        self::assertSame(2,$admin['doctorActs'][0]['acts']);
        self::assertSame([['label'=>'Cash','total'=>100.0]],$admin['financeEntries']['byMode']);
        self::assertSame([['date'=>'2026-11-02','entries'=>100.0,'exits'=>25.0,'net'=>75.0]],$admin['financeOut']['daily']);
        self::assertSame([['date'=>'2026-11-02','balance'=>75.0]],$admin['capitalEvolution']['daily']);
        $doctor = $this->dashboard->getMedecinCarousels($this->doctorA,$this->from,$this->to);
        self::assertSame(2.0,$doctor['consultationsByWeekday'][0]['total']);
        self::assertSame(30.0,$doctor['revenuesByWeekday'][0]['total']);
        $tabs = $this->dashboard->getAdminTabs($this->from,$this->to);
        self::assertCount(2,$tabs['appointments']); self::assertCount(2,$tabs['pendingConsultations']); self::assertCount(2,$tabs['unpaidInvoices']);
        self::assertSame('180 min',$tabs['pendingConsultations'][0]['waitingTime']);
        $doctorTabs = $this->dashboard->getMedecinTabs($this->doctorA,$this->from,$this->to);
        self::assertCount(1,$doctorTabs['appointments']); self::assertCount(1,$doctorTabs['invoices']);
        self::assertSame([['label'=>'Cleaning','total'=>2]],$doctorTabs['topActs']);
        $reception = $this->dashboard->getReceptionTabs($this->from,$this->to);
        self::assertCount(2,$reception['payments']);
        $report = $this->dashboard->getReceptionCarousels($this->from,$this->to)['doctorReports'];
        self::assertSame(['total'=>3],$report['kpi']); self::assertSame('02/11/2026',$report['period']['from']);
    }

    public function testCacheSeparatesDifferentTimeRangesOnTheSameDay(): void
    {
        $morning = $this->dashboard->getAdminCards($this->from,new \DateTimeImmutable('2026-11-02 12:00:00'));
        $afternoon = $this->dashboard->getAdminCards(new \DateTimeImmutable('2026-11-02 14:00:00'),$this->to);
        self::assertSame(3,$morning['consultations']['total']);
        self::assertSame(0,$afternoon['consultations']['total']);
    }

    public function testPaidConsultationQueryIncludesInsuranceButNotAnActsInvoiceAlone(): void
    {
        $patient = $this->patient('Insured');
        $consultation = $this->consultation($patient,$this->doctorB,'11:00:00');
        $insurer = (new Assurance())->setNom('Insurance')->setCode('TEST');
        $claim = (new FactureAssurance())->setPatient($patient)->setAssurance($insurer)->setConsultation($consultation)
            ->setDateFacture(new \DateTime('2026-11-02'))->setIsConsultationPayante(true);
        $consultation->setFactureAssurance($claim);
        $this->em->persist($insurer); $this->em->persist($claim); $this->em->flush();
        self::assertSame(2,$this->queries->countPaidConsultations($this->from,$this->to));
        self::assertSame(1,$this->queries->countPaidConsultations($this->from,$this->to,$this->doctorB));
    }

    private function doctor(string $name): Employe
    {
        $doctor = (new Employe())->setNom($name)->setPrenom('Doctor')->setFonction('Doctor')->setType('medecin')
            ->setDateEmbauche(new \DateTime('2020-01-01'))->setTypeSalaire('fixe')->setTypeContrat('CDI');
        $this->em->persist($doctor); return $doctor;
    }

    private function patient(string $name): Patient
    {
        $patient = (new Patient())->setNom($name)->setPrenom('Test')->setTelephone('123')->setSexe('M')->setAdresse('Test')
            ->setGroupeSanguin('O+')->setNumCarnet(uniqid('DASH',true))->setReferencement('Test')->setDateInscription(new \DateTime('2026-11-02 08:30:00'));
        $this->em->persist($patient); return $patient;
    }

    private function consultation(Patient $patient, Employe $doctor, string $time): Consultation
    {
        $consultation = (new Consultation())->setPatient($patient)->setMedecin($doctor)->setCreatedAt(new \DateTime('2026-11-02 '.$time))->setStatut(0);
        $this->em->persist($consultation); return $consultation;
    }

    private function invoice(Consultation $consultation, string $type, float $price, int $quantity): Facture
    {
        $act = (new ActeMedical())->setConsultation($consultation)->setType($type)->setPrix($price)->setQuantite($quantity);
        $consultation->addActe($act);
        $invoice = (new Facture())->setConsultation($consultation)->setDateFacture(new \DateTime('2026-11-02 11:00:00'));
        $consultation->setFacture($invoice); $this->em->persist($invoice); return $invoice;
    }

    private function appointment(Patient $patient, Employe $doctor, int $status, string $time): void
    {
        $appointment = (new Rdv())->setPatient($patient)->setMedecin($doctor)->setStatut($status)->setDateCreation(new \DateTime('2026-11-02'))
            ->setDateRdv(new \DateTime('2026-11-02 '.$time))->setDescription('Visit')->setDuration(30);
        $this->em->persist($appointment);
    }
}
