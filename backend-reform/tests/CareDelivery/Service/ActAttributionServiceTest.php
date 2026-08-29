<?php

namespace App\Tests\CareDelivery\Service;

use App\CareDelivery\Entity\ActeMedical;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Service\ActAttributionService;
use PHPUnit\Framework\TestCase;

class ActAttributionServiceTest extends TestCase
{
    private ActAttributionService $service;

    protected function setUp(): void
    {
        $this->service = new ActAttributionService();
    }

    public function testAllocateAmountMixed(): void
    {
        $result = $this->service->allocateAmount(10000, 7000, 10000);

        $this->assertSame(7000.0, $result['medecin']);
        $this->assertSame(3000.0, $result['cabinet']);
    }

    public function testAllocateAmountPartialPayment(): void
    {
        $result = $this->service->allocateAmount(5000, 7000, 10000);

        $this->assertSame(3500.0, $result['medecin']);
        $this->assertSame(1500.0, $result['cabinet']);
    }

    public function testAllocateAmountWhenTotalIsZero(): void
    {
        $result = $this->service->allocateAmount(5000, 0, 0);

        $this->assertSame(0.0, $result['medecin']);
        $this->assertSame(0.0, $result['cabinet']);
    }

    public function testSplitConsultationAmountsWithConsultationFee(): void
    {
        $consultation = $this->buildConsultationWithActs(10000, 5000, 3000);

        $split = $this->service->splitConsultationAmounts($consultation, true, 3000);

        $this->assertSame(10000.0, $split['medecinActs']);
        $this->assertSame(5000.0, $split['cabinetActs']);
        $this->assertSame(13000.0, $split['medecinBillable']);
        $this->assertSame(5000.0, $split['cabinetBillable']);
        $this->assertSame(18000.0, $split['totalBillable']);
        $this->assertEqualsWithDelta(13000 / 18000, $split['medecinRatio'], 0.0001);
    }

    public function testSplitConsultationAmountsCabinetOnly(): void
    {
        $consultation = $this->buildConsultationWithActs(0, 8000, 0);

        $split = $this->service->splitConsultationAmounts($consultation, true, 0);

        $this->assertSame(0.0, $split['medecinActs']);
        $this->assertSame(8000.0, $split['cabinetActs']);
        $this->assertSame(0.0, $split['medecinBillable']);
        $this->assertSame(8000.0, $split['cabinetBillable']);
    }

    private function buildConsultationWithActs(float $medecinAmount, float $cabinetAmount, float $consultationFee): Consultation
    {
        $consultation = new Consultation();

        if ($medecinAmount > 0) {
            $medecinAct = (new ActeMedical())
                ->setType('Extraction')
                ->setPrix($medecinAmount)
                ->setQuantite(1)
                ->setAttribution('medecin');
            $consultation->addActe($medecinAct);
        }

        if ($cabinetAmount > 0) {
            $cabinetAct = (new ActeMedical())
                ->setType('Radio')
                ->setPrix($cabinetAmount)
                ->setQuantite(1)
                ->setAttribution('cabinet');
            $consultation->addActe($cabinetAct);
        }

        return $consultation;
    }
}
