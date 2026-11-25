<?php

namespace App\Tests\Billing;

use App\Billing\Application\Service\CabinetServiceBillingService;
use App\Billing\Application\Service\Workflow\ClassicInvoiceWorkflowService;
use App\Billing\Infrastructure\Persistence\Doctrine\DevisRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\FactureRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\ModeDePaiementRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\PaiementRepository;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\ConsultationRepository;
use App\ClinicalRecord\Application\Service\FicheMedicaleService;
use App\ClinicalRecord\Domain\Entity\FicheMedicale;
use App\ClinicalRecord\Infrastructure\Persistence\Doctrine\FicheMedicaleRepository;
use App\Patient\Domain\Entity\Patient;
use App\Patient\Infrastructure\Persistence\Doctrine\PatientRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class DevisIntegrationTest extends TestCase
{
    public function testQuoteLinesAreSavedReloadedAndPrinted(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(glob(dirname(__DIR__, 2).'/src/*/Domain/Entity'), true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $patient = (new Patient())->setNom('Test')->setPrenom('Patient')->setTelephone('12345')
            ->setAdresse('Test')->setSexe('M')->setGroupeSanguin('O+')->setNumCarnet('TEST-1')->setReferencement('Test')->setDateInscription(new \DateTime());
        $fiche = (new FicheMedicale())->setPatient($patient);
        $em->persist($patient);
        $em->persist($fiche);
        $em->flush();
        $ficheId = $fiche->getId();

        $devisRepo = new DevisRepository($registry);
        $service = new FicheMedicaleService(
            $em, $devisRepo, new ConsultationRepository($registry), new FicheMedicaleRepository($registry),
            new PatientRepository($registry), $this->createMock(CabinetServiceBillingService::class),
            new ParameterBag(['kernel.project_dir' => dirname(__DIR__, 2)]),
        );
        $service->updateDevis($ficheId, ['contenus' => [
            ['diagnostic' => ' Carie ', 'traitement' => 'Composite', 'qte' => 2, 'montant' => 7500],
            ['designation' => 'Détartrage', 'qte' => 1, 'montant' => 6000],
        ]]);
        $em->clear();

        $devis = $service->getFicheJson($ficheId)['devis']['devisList'][0];
        self::assertSame(['Carie', null], array_column($devis['contenus'], 'diagnostic'));
        self::assertSame(['Composite', 'Détartrage'], array_column($devis['contenus'], 'traitement'));
        self::assertSame(21000.0, $devis['montant']);

        $workflow = new ClassicInvoiceWorkflowService(
            new FactureRepository($registry), $devisRepo, new PaiementRepository($registry), new ModeDePaiementRepository($registry), $em,
        );
        $print = $workflow->previewDevis($devis['id']);
        self::assertSame(['Carie', null], array_column($print['contenus'], 'diagnostic'));
        self::assertSame(['Composite', 'Détartrage'], array_column($print['contenus'], 'traitement'));
        self::assertSame([15000.0, 6000.0], array_column($print['contenus'], 'total'));
    }
}
