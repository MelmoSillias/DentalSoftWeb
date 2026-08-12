<?php

namespace App\Tests\Unit\CareDelivery\Domain\Model;

use App\CareDelivery\Domain\Exception\CareDeliveryDomainException;
use App\CareDelivery\Domain\Model\ActeMedical;
use App\CareDelivery\Domain\Model\Consultation;
use App\CareDelivery\Domain\ValueObject\ConsultationId;
use PHPUnit\Framework\TestCase;

final class ConsultationTest extends TestCase
{
    public function testCreateOpensConsultation(): void
    {
        $consultation = Consultation::create(10, 5);

        self::assertNull($consultation->getId());
        self::assertTrue($consultation->isOpen());
        self::assertSame(10, $consultation->getPatientId());
        self::assertSame(5, $consultation->getMedecinId());
        self::assertSame([], $consultation->getActes());
    }

    public function testAssignMedecinIfUnassigned(): void
    {
        $consultation = Consultation::create(10);

        $consultation->assignMedecinIfUnassigned(7);

        self::assertSame(7, $consultation->getMedecinId());

        $consultation->assignMedecinIfUnassigned(7);
        self::assertSame(7, $consultation->getMedecinId());
    }

    public function testAssignMedecinRejectedWhenDifferentAlreadyAssigned(): void
    {
        $consultation = Consultation::create(10, 3);

        $this->expectException(CareDeliveryDomainException::class);
        $this->expectExceptionMessage('Consultation is already assigned to a different medecin.');

        $consultation->assignMedecinIfUnassigned(9);
    }

    public function testRequireMedecinForClose(): void
    {
        $consultation = Consultation::create(10);

        $this->expectException(CareDeliveryDomainException::class);
        $this->expectExceptionMessage('Consultation requires a medecin before close.');

        $consultation->requireMedecinForClose();
    }

    public function testReplaceActesWhenClosedThrows(): void
    {
        $consultation = Consultation::reconstitute(ConsultationId::fromInt(1), 10, 1, 2);
        $acte = new ActeMedical('Detartrage', null, null, 100.0, 1);

        $this->expectException(CareDeliveryDomainException::class);
        $this->expectExceptionMessage('Consultation is not open.');

        $consultation->replaceActes([$acte]);
    }

    public function testReplaceActesWhenOpen(): void
    {
        $consultation = Consultation::create(10, 2);
        $acte = new ActeMedical('Detartrage', '16', 'desc', 100.0, 2);

        $consultation->replaceActes([$acte]);

        self::assertCount(1, $consultation->getActes());
        self::assertSame('Detartrage', $consultation->getActes()[0]->getType());
    }

    public function testCloseTransitionsOpenToClosed(): void
    {
        $consultation = Consultation::reconstitute(ConsultationId::fromInt(1), 10, 0);

        self::assertTrue($consultation->isOpen());
        self::assertFalse($consultation->isClosed());

        $consultation->close();

        self::assertTrue($consultation->isClosed());
        self::assertFalse($consultation->isOpen());
        self::assertSame(1, $consultation->getStatus());
    }

    public function testCloseRejectedWhenAlreadyClosed(): void
    {
        $consultation = Consultation::reconstitute(ConsultationId::fromInt(1), 10, 1);

        $this->expectException(CareDeliveryDomainException::class);
        $this->expectExceptionMessage('Consultation is already closed.');

        $consultation->close();
    }

    public function testReopenTransitionsClosedToOpen(): void
    {
        $consultation = Consultation::reconstitute(ConsultationId::fromInt(2), 11, 1);

        $consultation->reopen();

        self::assertTrue($consultation->isOpen());
        self::assertSame(0, $consultation->getStatus());
    }

    public function testCancelRejectedWhenClosed(): void
    {
        $consultation = Consultation::reconstitute(ConsultationId::fromInt(3), 12, 1);

        $this->expectException(CareDeliveryDomainException::class);
        $this->expectExceptionMessage('Closed consultation cannot be cancelled.');

        $consultation->cancel();
    }

    public function testCancelMarksOpenConsultation(): void
    {
        $consultation = Consultation::reconstitute(ConsultationId::fromInt(4), 13, 0);

        $consultation->cancel();

        self::assertTrue($consultation->isCancelled());
        self::assertFalse($consultation->isOpen());
    }
}
