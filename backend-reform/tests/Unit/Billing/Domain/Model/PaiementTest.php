<?php

namespace App\Tests\Unit\Billing\Domain\Model;

use App\Billing\Domain\Exception\BillingDomainException;
use App\Billing\Domain\Model\Paiement;
use App\Billing\Domain\ValueObject\PaiementId;
use PHPUnit\Framework\TestCase;

final class PaiementTest extends TestCase
{
    public function testCreateRequiresPositiveAmount(): void
    {
        $paiement = Paiement::create(100.0);

        self::assertSame(100.0, $paiement->getAmount());
        self::assertNull($paiement->getId());
    }

    public function testZeroAmountRejected(): void
    {
        $this->expectException(BillingDomainException::class);
        $this->expectExceptionMessage('Paiement amount must be greater than zero.');

        Paiement::create(0.0);
    }

    public function testNegativeAmountRejectedOnReconstitute(): void
    {
        $this->expectException(BillingDomainException::class);

        Paiement::reconstitute(PaiementId::fromInt(1), -1.0);
    }
}
