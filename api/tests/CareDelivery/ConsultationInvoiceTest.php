<?php

namespace App\Tests\CareDelivery;

use App\Billing\Domain\Entity\Facture;
use App\Billing\Domain\Entity\FactureAssurance;
use App\Billing\Domain\Entity\LotFactureAssurance;
use App\Billing\Domain\Entity\Paiement;
use App\Billing\Domain\Entity\Transaction;
use App\CareDelivery\Application\Mapper\ConsultationInvoiceMapper;
use App\CareDelivery\Application\Policy\ConsultationInvoicePolicy;
use App\CareDelivery\Application\Service\ConsultationService;
use App\CareDelivery\Domain\Entity\ActeMedical;
use App\CareDelivery\Domain\Entity\Consultation;
use PHPUnit\Framework\TestCase;

final class ConsultationInvoiceTest extends TestCase
{
    private ConsultationInvoiceMapper $mapper;
    private ConsultationInvoicePolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ConsultationInvoicePolicy();
        $this->mapper = new ConsultationInvoiceMapper($this->policy);
    }

    public function testMissingInvoicePreservesAllNullFields(): void
    {
        self::assertSame([
            'factureId' => null, 'factureAssuranceId' => null, 'factureType' => null,
            'factureMontant' => null, 'factureReste' => null, 'factureReglee' => null,
            'factureInsuranceStatus' => null, 'insuranceRate' => null, 'insuranceAmount' => null,
            'patientPaidAmount' => null, 'factstate' => null,
        ], $this->mapper->invoice(new Consultation()));
        self::assertFalse($this->policy->isModifiable(null));
    }

    public function testDirectInvoicePreservesTotalsAndPaymentFields(): void
    {
        $consultation = $this->consultationWithAct(100, 2);
        $facture = new Facture();
        $consultation->setFacture($facture);
        (new \ReflectionProperty(Facture::class, 'id'))->setValue($facture, 8);
        $facture->addPaiement((new Paiement())->setMontant(50));
        self::assertSame([
            'factureId' => 8, 'factureAssuranceId' => null, 'factureType' => 'Facture',
            'factureMontant' => 200.0, 'factureReste' => 150.0, 'factureReglee' => false,
            'factureInsuranceStatus' => null, 'insuranceRate' => null, 'insuranceAmount' => null,
            'patientPaidAmount' => 50.0, 'factstate' => 0,
        ], $this->mapper->invoice($consultation));
        $facture->addPaiement((new Paiement())->setMontant(200));
        $data = $this->mapper->invoice($consultation);
        self::assertSame(0.0, $data['factureReste']);
        self::assertSame(200.0, $data['patientPaidAmount']);
        self::assertTrue($data['factureReglee']);
        self::assertSame(1, $data['factstate']);
    }

    /** @dataProvider emptyInvoiceStates */
    public function testEmptyDirectInvoiceUsesExplicitValidationFlag(bool $validated): void
    {
        $consultation = (new Consultation())->setFacture((new Facture())->setIsReglee($validated));
        $data = $this->mapper->invoice($consultation);
        self::assertSame(0.0, $data['factureMontant']);
        self::assertSame($validated, $data['factureReglee']);
        self::assertSame($validated ? 1 : 0, $data['factstate']);
    }

    public static function emptyInvoiceStates(): array
    {
        return [[false], [true]];
    }

    public function testInsuranceTakesPrecedenceAndRetainsActualPaidAmount(): void
    {
        $consultation = $this->consultationWithAct(100, 2);
        $consultation->setFacture(new Facture());
        $insurance = (new FactureAssurance())->setCoverageRate(75)->setInsuranceStatus('ready');
        $consultation->setFactureAssurance($insurance);
        (new \ReflectionProperty(FactureAssurance::class, 'id'))->setValue($insurance, 9);
        $insurance->addPaiement((new Paiement())->setMontant(60));
        self::assertSame([
            'factureId' => 9, 'factureAssuranceId' => 9, 'factureType' => 'FactureAssurance',
            'factureMontant' => 50.0, 'factureReste' => 0.0, 'factureReglee' => true,
            'factureInsuranceStatus' => 'ready', 'insuranceRate' => 75.0, 'insuranceAmount' => 150.0,
            'patientPaidAmount' => 60.0, 'factstate' => 1,
        ], $this->mapper->invoice($consultation));
    }

    /** @dataProvider insuranceStates */
    public function testInsuranceSettlementRules(float $price, ?float $rate, string $status, bool $settled): void
    {
        $consultation = $this->consultationWithAct($price, 1);
        $consultation->setFactureAssurance((new FactureAssurance())->setCoverageRate($rate)->setInsuranceStatus($status));
        $data = $this->mapper->invoice($consultation);
        self::assertSame($settled, $data['factureReglee']);
        self::assertSame($settled ? 1 : 0, $data['factstate']);
        self::assertSame((float) ($rate ?? 0), $data['insuranceRate']);
    }

    public static function insuranceStates(): array
    {
        return [
            'empty pending' => [0.0, null, 'pending', false],
            'empty validated' => [0.0, null, 'validated_empty', true],
            'fully covered' => [100.0, 100.0, 'ready', true],
            'partly covered' => [100.0, 75.0, 'ready', false],
        ];
    }

    /** @dataProvider lotStates */
    public function testInsuranceLotStateControlsEditability(string $status, bool $modifiable): void
    {
        $consultation = (new Consultation())->setFacture(new Facture());
        $consultation->setFactureAssurance((new FactureAssurance())->setLotFactureAssurance((new LotFactureAssurance())->setStatut($status)));
        self::assertSame($modifiable, $this->policy->isModifiable($consultation->getFacture()));
    }

    public static function lotStates(): array
    {
        return [
            ['ouvert', true], ['envoye', false], ['confirme', false],
            ['partiellement_rembourse', false], ['rembourse', false], ['recouvre', false],
        ];
    }

    public function testAnyDirectPaymentBlocksEditingEvenWhenUnvalidated(): void
    {
        $facture = new Facture();
        self::assertTrue($this->policy->isModifiable($facture));
        $facture->addPaiement((new Paiement())->setMontant(0)->setTransaction((new Transaction())->setValidationStatus('pending')));
        self::assertFalse($this->policy->isModifiable($facture));
    }

    public function testOnlyValidatedInsurancePaymentsBlockEditing(): void
    {
        $consultation = (new Consultation())->setFacture(new Facture());
        $insurance = new FactureAssurance();
        $consultation->setFactureAssurance($insurance);
        self::assertTrue($this->policy->isModifiable($consultation->getFacture()));
        $insurance->addPaiement((new Paiement())->setMontant(20)->setTransaction((new Transaction())->setValidationStatus('pending')));
        self::assertTrue($this->policy->isModifiable($consultation->getFacture()));
        $insurance->addPaiement((new Paiement())->setMontant(1));
        self::assertFalse($this->policy->isModifiable($consultation->getFacture()));
    }

    public function testPatientSummaryKeepsNormalizedActsAndClosedStateRequirement(): void
    {
        $consultation = $this->consultationWithAct(12.5, 0)->setCreatedAt(new \DateTime('2026-11-02 09:30:00'))->setStatut(1);
        $consultation->setFacture(new Facture());
        $act = $consultation->getActes()->first();
        $act->setType('Cleaning')->setDescription('Description')->setDent('11,12');
        $facade = (new \ReflectionClass(ConsultationService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(ConsultationService::class, 'invoiceMapper'))->setValue($facade, $this->mapper);
        self::assertSame([
            'id' => null, 'date' => '2026-11-02 09:30', 'statut' => 1, 'state' => 1,
            'medecin' => null, 'factureMontant' => 12.5, 'factureStatut' => 0,
            'factureId' => null, 'factModifiable' => true,
            'actes' => [[
                'id' => null, 'type' => 'Cleaning', 'description' => 'Description', 'dent' => '11,12',
                'prix' => 12.5, 'quantite' => 1, 'montant' => 12.5,
            ]],
        ], $facade->formatPatientConsultationSummary($consultation));
        $consultation->setStatut(0);
        self::assertFalse($facade->formatPatientConsultationSummary($consultation)['factModifiable']);
        $consultation->setFacture(null)->setCreatedAt(null);
        $data = $facade->formatPatientConsultationSummary($consultation);
        self::assertNull($data['date']);
        self::assertNull($data['factureMontant']);
        self::assertSame(0, $data['factureStatut']);
    }

    private function consultationWithAct(float $price, int $quantity): Consultation
    {
        return (new Consultation())->addActe((new ActeMedical())->setPrix($price)->setQuantite($quantity));
    }
}
