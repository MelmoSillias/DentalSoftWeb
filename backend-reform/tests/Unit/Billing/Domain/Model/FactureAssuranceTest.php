<?php

namespace App\Tests\Unit\Billing\Domain\Model;

use App\Billing\Domain\Exception\BillingDomainException;
use App\Billing\Domain\Model\FactureAssurance;
use App\Billing\Domain\ValueObject\FactureAssuranceId;
use App\Billing\Domain\ValueObject\InsuranceStatus;
use PHPUnit\Framework\TestCase;

final class FactureAssuranceTest extends TestCase
{
    public function testMarkReadyTransitionsPendingToReady(): void
    {
        $claim = FactureAssurance::reconstitute(
            FactureAssuranceId::fromInt(1),
            InsuranceStatus::PENDING,
        );

        $claim->markReady();

        self::assertTrue($claim->isReady());
        self::assertSame(InsuranceStatus::READY, $claim->getInsuranceStatusValue());
    }

    public function testMarkReadyAllowsOpenStatus(): void
    {
        $claim = FactureAssurance::reconstitute(
            FactureAssuranceId::fromInt(2),
            InsuranceStatus::OPEN,
        );

        $claim->markReady();

        self::assertTrue($claim->isReady());
    }

    public function testMarkReadyRejectedWhenAlreadyReady(): void
    {
        $claim = FactureAssurance::reconstitute(
            FactureAssuranceId::fromInt(3),
            InsuranceStatus::READY,
        );

        $this->expectException(BillingDomainException::class);
        $this->expectExceptionMessage('Insurance claim is already ready.');

        $claim->markReady();
    }

    public function testMarkReadyRejectedFromRembourse(): void
    {
        $claim = FactureAssurance::reconstitute(
            FactureAssuranceId::fromInt(4),
            InsuranceStatus::REMBOURSE,
        );

        $this->expectException(BillingDomainException::class);
        $this->expectExceptionMessage('Cannot mark insurance claim ready from status "rembourse".');

        $claim->markReady();
    }
}
