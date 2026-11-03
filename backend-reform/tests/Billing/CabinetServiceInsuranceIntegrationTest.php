<?php

namespace App\Tests\Billing;

use App\Billing\Entity\Assurance;
use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\FactureCabinet;
use App\Billing\Entity\ModeDePaiement;
use App\Billing\Entity\ServiceCabinet;
use App\Billing\Repository\AssuranceRepository;
use App\Billing\Repository\FactureAssuranceRepository;
use App\Billing\Repository\FactureCabinetRepository;
use App\Billing\Repository\FactureRepository;
use App\Billing\Repository\LotFactureAssuranceRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Billing\Repository\ServiceCabinetRepository;
use App\Billing\Service\CabinetServiceBillingService;
use App\Billing\Service\InsuranceClaimService;
use App\Billing\Service\IntegratedInsuranceCatalog;
use App\Billing\Service\LotFactureAssuranceService;
use App\Billing\Service\Workflow\InsuredInvoiceWorkflowService;
use App\ClinicalRecord\Repository\FicheMedicaleRepository;
use App\Patient\Entity\Patient;
use App\Patient\Entity\PatientAssuranceProfile;
use App\Patient\Repository\PatientRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class CabinetServiceInsuranceIntegrationTest extends TestCase
{
    public function testServiceInsuranceLifecycleInIsolatedDatabase(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite is required');
        $config = ORMSetup::createAttributeMetadataConfiguration(glob(dirname(__DIR__, 2).'/src/*/Entity'), true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $validator = new SchemaValidator($em);
        foreach ([FactureAssurance::class, ServiceCabinet::class] as $class) {
            self::assertSame([], $validator->validateClass($em->getClassMetadata($class)));
        }

        $assurance = (new Assurance())->setCode('TEST')->setNom('Assurance test');
        $patient = (new Patient())->setNom('Test')->setPrenom('Patient')->setTelephone('12345')
            ->setAdresse('Test')->setSexe('M')->setGroupeSanguin('O+')->setNumCarnet('TEST-1')->setReferencement('Test')->setDateInscription(new \DateTime());
        $profile = (new PatientAssuranceProfile())->setAssurance($assurance)->setCoverageRate(80)->setFormData(['numero' => 'ABC']);
        $patient->setAssuranceProfile($profile);
        $mode = (new ModeDePaiement())->setLibelle('Espèces')->setType('cash');
        $em->persist($assurance);
        $em->persist($patient);
        $em->persist($mode);
        $em->flush();

        $claims = new InsuranceClaimService(new FactureAssuranceRepository($registry), new ModeDePaiementRepository($registry), $em);
        $workflow = new InsuredInvoiceWorkflowService($claims, new FactureAssuranceRepository($registry));
        $billing = new CabinetServiceBillingService($em, new ServiceCabinetRepository($registry), new FactureCabinetRepository($registry),
            new ModeDePaiementRepository($registry), new PatientRepository($registry), $this->createMock(FicheMedicaleRepository::class), $claims, $workflow);
        $lots = new LotFactureAssuranceService(new LotFactureAssuranceRepository($registry), new AssuranceRepository($registry),
            new FactureAssuranceRepository($registry), new ModeDePaiementRepository($registry), $this->createMock(IntegratedInsuranceCatalog::class), $em);
        $created = $billing->createForPatient($patient->getId(), ['designation' => 'Radio', 'prix' => 10000, 'coveredByInsurance' => true])['data'];
        $invoiceId = $created['facture']['id'];
        self::assertCount(1, $claims->listClaims('ready'));
        self::assertCount(1, $lots->listLots('TEST')['unassignedClaims']);
        self::assertCount(1, $workflow->listUnpaidCabinetFactures(null, null));
        self::assertSame([], $billing->listUnpaidForCashdesk(null, null));
        self::assertSame('0', (string) $em->createQuery('SELECT COUNT(f.id) FROM '.FactureCabinet::class.' f')->getSingleScalarResult());
        // The existing consultation-debt SQL uses MariaDB functions; exercise the
        // shared cabinet-debt query directly against SQLite instead.
        $debtQuery = new \ReflectionMethod(FactureRepository::class, 'unpaidCabinetRows');
        $debts = $debtQuery->invoke(new FactureRepository($registry), [$patient->getId()]);
        self::assertEquals(2000, $debts[0]['reste']);
        self::assertEquals(2000, $workflow->mapFactureAssurancePrint($invoiceId)['montantPatient']);

        self::assertEquals(1500, $claims->payPatientShare($invoiceId, $mode->getId(), 500)['restePatient']);
        self::assertEquals(0, $claims->payPatientShare($invoiceId, $mode->getId(), 1500)['restePatient']);
        self::assertSame([], $workflow->listUnpaidCabinetFactures(null, null));
        $lotId = $lots->openLot('TEST')['lotId'];
        self::assertTrue($lots->addClaimToLot($lotId, $invoiceId)['success']);
        self::assertEquals(8000, $lots->getLot($lotId)['data']['montantTotal']);
        self::assertTrue($lots->sendLot($lotId)['success']);
        self::assertTrue($lots->confirmLot($lotId)['success']);
        self::assertEquals(5000, $lots->refundLot($lotId, $mode->getId(), 3000)['resteARembourser']);
        self::assertSame('partiellement_rembourse', $lots->getLot($lotId)['data']['statut']);
        self::assertEquals(0, $lots->refundLot($lotId, $mode->getId(), 5000)['resteARembourser']);
        self::assertTrue($claims->getClaimDetail($invoiceId)['data']['isRecouvre']);

        $cancelled = $billing->createForPatient($patient->getId(), ['designation' => 'Autre', 'prix' => 5000, 'coveredByInsurance' => true])['data'];
        self::assertTrue($billing->cancel($cancelled['id'])['success']);
        self::assertCount(1, $claims->listClaims());
        self::assertSame(400, $claims->payPatientShare($cancelled['facture']['id'], $mode->getId(), 100)['status']);
        self::assertSame(400, $lots->addClaimToLot($lots->openLot('TEST')['lotId'], $cancelled['facture']['id'])['status']);
        $summary = $billing->summarizePeriod(new \DateTime('today'), new \DateTime('tomorrow'));
        self::assertEquals(10000, $summary['facture']);
        self::assertEquals(2000, $summary['encaisse']);
        self::assertEquals(0, $summary['reste']);

        $profile->setCoverageRate(50)->setFormData(['numero' => 'CHANGED']);
        $em->flush();
        $em->clear();
        self::assertEquals(8000, $claims->getClaimDetail($invoiceId)['data']['montantAssurance']);
        self::assertSame('ABC', $claims->getClaimDetail($invoiceId)['data']['assuranceSnapshot']['formData']['numero']);
        self::assertTrue($billing->get($created['id'])['coveredByInsurance']);
        $em->close();
    }
}
