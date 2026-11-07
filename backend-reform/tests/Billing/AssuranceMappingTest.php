<?php

namespace App\Tests\Billing;

use App\Billing\Entity\Assurance;
use App\Billing\Entity\FactureAssurance;
use App\CareDelivery\Entity\Consultation;
use App\Patient\Entity\Patient;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\TestCase;

class AssuranceMappingTest extends TestCase
{
    public function testInvoicesLoadForTheirInsurerAndCanBeReassigned(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(glob(dirname(__DIR__, 2).'/src/*/Entity'), true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

        try {
            self::assertSame([], (new SchemaValidator($em))->validateMapping());
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

            $first = (new Assurance())->setNom('First');
            $second = (new Assurance())->setNom('Second');
            self::assertCount(0, $first->getFactures());
            $patient = (new Patient())->setNom('Test')->setPrenom('Patient')->setTelephone('12345')
                ->setAdresse('Test')->setSexe('M')->setGroupeSanguin('O+')->setNumCarnet('TEST-ASSURANCE')
                ->setReferencement('Test')->setDateInscription(new \DateTime());
            $consultation = (new Consultation())->setPatient($patient)->setCreatedAt(new \DateTime());
            $invoice = (new FactureAssurance())->setAssurance($first)->setPatient($patient)
                ->setConsultation($consultation)->setDateFacture(new \DateTime());
            foreach ([$first, $second, $patient, $consultation, $invoice] as $entity) {
                $em->persist($entity);
            }
            $em->flush();
            $firstId = $first->getId();
            $secondId = $second->getId();
            $invoiceId = $invoice->getId();
            $em->clear();

            $first = $em->find(Assurance::class, $firstId);
            $second = $em->find(Assurance::class, $secondId);
            self::assertCount(1, $first->getFactures());
            self::assertSame($invoiceId, $first->getFactures()->first()->getId());
            self::assertSame($first, $first->getFactures()->first()->getAssurance());
            self::assertCount(0, $second->getFactures());

            // The existing setter remains the owning-side API for persistence.
            $invoice = $em->find(FactureAssurance::class, $invoiceId);
            self::assertSame($invoice, $invoice->setAssurance($second));
            $em->flush();
            $em->clear();

            self::assertCount(0, $em->find(Assurance::class, $firstId)->getFactures());
            $invoices = $em->find(Assurance::class, $secondId)->getFactures();
            self::assertCount(1, $invoices);
            self::assertSame($invoiceId, $invoices->first()->getId());
        } finally {
            $em->close();
        }
    }
}
