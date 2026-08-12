<?php

namespace App\Tests\Unit\Billing\Domain\Model;

use App\Billing\Domain\Exception\BillingDomainException;
use App\Billing\Domain\Model\Devis;
use App\Billing\Domain\ValueObject\DevisId;
use PHPUnit\Framework\TestCase;

final class DevisTest extends TestCase
{
    public function testValidateTransitionsDraftToValidated(): void
    {
        $devis = Devis::reconstitute(DevisId::fromInt(1), Devis::STATUS_DRAFT);

        $devis->validate();

        self::assertTrue($devis->isValidated());
        self::assertSame(Devis::STATUS_VALIDATED, $devis->getStatus());
    }

    public function testValidateRejectedWhenAlreadyValidated(): void
    {
        $devis = Devis::reconstitute(DevisId::fromInt(1), Devis::STATUS_VALIDATED);

        $this->expectException(BillingDomainException::class);
        $this->expectExceptionMessage('Devis is already validated.');

        $devis->validate();
    }

    public function testCancelMarksDevisCancelled(): void
    {
        $devis = Devis::reconstitute(DevisId::fromInt(2), Devis::STATUS_DRAFT);

        $devis->cancel();

        self::assertTrue($devis->isCancelled());
        self::assertSame(Devis::STATUS_CANCELLED, $devis->getStatus());
    }

    public function testCancelRejectedWhenAlreadyCancelled(): void
    {
        $devis = Devis::reconstitute(DevisId::fromInt(3), Devis::STATUS_CANCELLED);

        $this->expectException(BillingDomainException::class);
        $this->expectExceptionMessage('Devis is already cancelled.');

        $devis->cancel();
    }

    public function testValidateRejectedWhenCancelled(): void
    {
        $devis = Devis::reconstitute(DevisId::fromInt(4), Devis::STATUS_DRAFT, true);

        $this->expectException(BillingDomainException::class);
        $this->expectExceptionMessage('Cancelled devis cannot be validated.');

        $devis->validate();
    }
}
