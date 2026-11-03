<?php

namespace App\Tests\Billing;

use App\Billing\Entity\Assurance;
use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\FactureCabinet;
use App\Billing\Entity\LotFactureAssurance;
use App\Billing\Entity\ModeDePaiement;
use App\Billing\Entity\Paiement;
use App\Billing\Entity\ServiceCabinet;
use App\Billing\Repository\FactureAssuranceRepository;
use App\Billing\Repository\FactureCabinetRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Billing\Repository\ServiceCabinetRepository;
use App\Billing\Service\CabinetServiceBillingService;
use App\Billing\Service\InsuranceClaimService;
use App\Billing\Service\Workflow\InsuredInvoiceWorkflowService;
use App\CareDelivery\Entity\Consultation;
use App\ClinicalRecord\Repository\FicheMedicaleRepository;
use App\Patient\Entity\Patient;
use App\Patient\Entity\PatientAssuranceProfile;
use App\Patient\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CabinetServiceInsuranceTest extends TestCase
{
    private function identified(object $entity, int $id = 1): object
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
        return $entity;
    }

    private function patient(float $rate = 80): Patient
    {
        $assurance = $this->identified((new Assurance())->setNom('Assurance test')->setCode('TEST')->setActif(true));
        $patient = $this->identified((new Patient())->setNom('Test')->setPrenom('Patient'));
        $patient->setAssuranceProfile((new PatientAssuranceProfile())->setAssurance($assurance)->setCoverageRate($rate)->setFormData(['numero' => 'ABC']));
        return $patient;
    }

    private function billing(Patient $patient, ?ServiceCabinet $existing, array &$persisted): CabinetServiceBillingService
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $this->identified($entity);
            $persisted[] = $entity;
        });
        $patients = $this->createMock(PatientRepository::class);
        $patients->method('find')->willReturn($patient);
        $services = $this->createMock(ServiceCabinetRepository::class);
        $services->method('find')->willReturn($existing);
        $claims = $this->createMock(InsuranceClaimService::class);
        $claims->method('getClaimDetail')->willReturn(['data' => []]);
        return new CabinetServiceBillingService($manager, $services,
            $this->createMock(FactureCabinetRepository::class), $this->createMock(ModeDePaiementRepository::class),
            $patients, $this->createMock(FicheMedicaleRepository::class), $claims,
            $this->createMock(InsuredInvoiceWorkflowService::class));
    }

    public function testCoveredServiceCreatesOnlyInsuranceInvoiceAndFreezesProfile(): void
    {
        $patient = $this->patient();
        $persisted = [];
        $result = $this->billing($patient, null, $persisted)->createForPatient(1, ['designation' => 'Radio', 'quantite' => 2, 'prix' => 5000, 'coveredByInsurance' => true]);
        self::assertTrue($result['success']);
        self::assertCount(2, $persisted);
        [$service, $invoice] = $persisted;
        self::assertInstanceOf(FactureAssurance::class, $invoice);
        self::assertNull($service->getFacture());
        self::assertSame($invoice, $service->getFactureAssurance());
        self::assertNull($invoice->getConsultation());
        self::assertTrue($invoice->isReadyForBilling());
        $patient->getAssuranceProfile()->setCoverageRate(50)->setFormData(['numero' => 'CHANGED']);
        $service->setPrix(99999)->setDesignation('Changed');
        self::assertEquals(2000, $invoice->computeTotals()['montantPatient']);
        self::assertEquals(8000, $invoice->computeTotals()['montantAssureur']);
        self::assertSame('Radio', $invoice->buildDisplayLignes()[0]['designation']);
        self::assertSame('ABC', $invoice->getAssuranceSnapshot()['formData']['numero']);
    }

    public function testInsuredPatientCanHaveNonCoveredService(): void
    {
        $persisted = [];
        $result = $this->billing($this->patient(), null, $persisted)->createForPatient(1, ['designation' => 'Radio', 'prix' => 10000, 'coveredByInsurance' => false]);
        self::assertTrue($result['success']);
        self::assertInstanceOf(FactureCabinet::class, $persisted[1]);
        self::assertNull($persisted[0]->getFactureAssurance());
        self::assertEquals(10000, $persisted[1]->computeReste());
    }

    public function testCoverageRequiresAnActiveInsuranceAndValidRate(): void
    {
        foreach ([null, 0, -1, 101] as $rate) {
            $patient = $this->patient();
            $patient->getAssuranceProfile()->setCoverageRate($rate);
            $persisted = [];
            self::assertSame(400, $this->billing($patient, null, $persisted)->createForPatient(1, ['designation' => 'Radio', 'prix' => 10000, 'coveredByInsurance' => true])['status']);
            self::assertSame([], $persisted);
        }
        $patient = $this->patient();
        $patient->getAssuranceProfile()->getAssurance()->setActif(false);
        $persisted = [];
        self::assertSame(400, $this->billing($patient, null, $persisted)->createForPatient(1, ['designation' => 'Radio', 'prix' => 10000, 'coveredByInsurance' => true])['status']);
    }

    private function insuredService(float $rate = 80): ServiceCabinet
    {
        $service = $this->identified((new ServiceCabinet())->setPatient($this->patient($rate))->setDesignation('Radio')->setPrix(10000)->setMontant(10000)->setDateRealisation(new \DateTime()));
        $this->identified((new FactureAssurance())->setServiceCabinet($service)->setPatient($service->getPatient())->setCoverageRate($rate)->setServiceSnapshot(['designation' => 'Radio', 'quantite' => 1, 'prix' => 10000, 'total' => 10000]));
        return $service;
    }

    public function testPaidOrAssignedServiceCannotBeModifiedOrCancelled(): void
    {
        foreach (['paid', 'lot'] as $lock) {
            $service = $this->insuredService();
            if ($lock === 'paid') $service->getFactureAssurance()->addPaiement((new Paiement())->setMontant(500));
            else $service->getFactureAssurance()->setLotFactureAssurance(new LotFactureAssurance());
            $persisted = [];
            $billing = $this->billing($service->getPatient(), $service, $persisted);
            self::assertSame(400, $billing->update(1, ['prix' => 5000])['status']);
            self::assertSame(400, $billing->cancel(1)['status']);
            self::assertFalse($service->isAnnule());
            self::assertEquals(10000, $service->getMontant());
        }
    }

    public function testUnpaidServiceCanBeCorrectedAndCancelledWithoutChangingCoverage(): void
    {
        $service = $this->insuredService();
        $persisted = [];
        $billing = $this->billing($service->getPatient(), $service, $persisted);
        self::assertSame(400, $billing->update(1, ['coveredByInsurance' => false])['status']);
        $service->getPatient()->getAssuranceProfile()->setCoverageRate(50);
        self::assertTrue($billing->update(1, ['prix' => 20000])['success']);
        self::assertEquals(16000, $service->getFactureAssurance()->computeTotals()['montantAssureur']);
        self::assertTrue($billing->cancel(1)['success']);
        self::assertFalse($service->getFactureAssurance()->isReadyForBilling());
    }

    public function testOnlyPatientShareCanBeCollectedAndPartialPaymentsAreSupported(): void
    {
        $service = $this->insuredService();
        $repository = $this->createMock(FactureAssuranceRepository::class);
        $repository->method('find')->willReturn($service->getFactureAssurance());
        $modes = $this->createMock(ModeDePaiementRepository::class);
        $modes->method('find')->willReturn(new ModeDePaiement());
        $claims = new InsuranceClaimService($repository, $modes, $this->createMock(EntityManagerInterface::class));
        self::assertSame(400, $claims->payPatientShare(1, 1, 10000)['status']);
        self::assertEquals(1500, $claims->payPatientShare(1, 1, 500)['restePatient']);
        self::assertEquals(0, $claims->payPatientShare(1, 1, 1500)['restePatient']);
        self::assertSame(400, $claims->payPatientShare(1, 1, 1)['status']);
        self::assertFalse($service->getFactureAssurance()->isRecouvre());
    }

    public function testFullCoverageHasNoPatientDebtAndRemainsAnInsuranceReceivable(): void
    {
        $service = $this->insuredService(100);
        $invoice = $service->getFactureAssurance();
        self::assertEquals(0, $invoice->computeTotals()['montantPatient']);
        self::assertEquals(10000, $invoice->computeTotals()['montantAssureur']);
        $workflow = new InsuredInvoiceWorkflowService($this->createMock(InsuranceClaimService::class), $this->createMock(FactureAssuranceRepository::class));
        $row = $workflow->mapClaimToCashdeskRow(['id' => 1, 'montantTotal' => 10000, 'montantPatient' => 0, 'montantAssurance' => 10000]);
        self::assertTrue($row['isRegle']);
        self::assertEquals(0, $row['reste']);
        self::assertFalse($invoice->isRecouvre());
    }

    public function testDuplicateOriginsAreRejected(): void
    {
        $service = $this->insuredService();
        $this->expectException(\LogicException::class);
        (new FactureCabinet())->setService($service);
    }

    public function testConsultationAndServiceCannotShareOneInvoice(): void
    {
        $invoice = $this->insuredService()->getFactureAssurance();
        $this->expectException(\LogicException::class);
        $invoice->setConsultation(new Consultation());
    }
}
