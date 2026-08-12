<?php

namespace App\Tests\Unit\Billing\Domain\Model;

use App\Billing\Domain\Exception\BillingDomainException;
use App\Billing\Domain\Model\LotFactureAssurance;
use App\Billing\Domain\ValueObject\LotFactureAssuranceId;
use App\Billing\Domain\ValueObject\LotStatus;
use PHPUnit\Framework\TestCase;

final class LotFactureAssuranceTest extends TestCase
{
    public function testSendTransitionsOuvertToEnvoye(): void
    {
        $lot = LotFactureAssurance::reconstitute(
            LotFactureAssuranceId::fromInt(1),
            LotStatus::OUVERT,
        );

        $lot->send();

        self::assertTrue($lot->isEnvoye());
        self::assertSame(LotStatus::ENVOYE, $lot->getStatusValue());
    }

    public function testReopenTransitionsEnvoyeToOuvert(): void
    {
        $lot = LotFactureAssurance::reconstitute(
            LotFactureAssuranceId::fromInt(2),
            LotStatus::ENVOYE,
        );

        $lot->reopen();

        self::assertTrue($lot->isOuvert());
    }

    public function testConfirmTransitionsEnvoyeToConfirme(): void
    {
        $lot = LotFactureAssurance::reconstitute(
            LotFactureAssuranceId::fromInt(3),
            LotStatus::ENVOYE,
        );

        $lot->confirm();

        self::assertTrue($lot->isConfirme());
    }

    public function testUnconfirmTransitionsConfirmeToEnvoye(): void
    {
        $lot = LotFactureAssurance::reconstitute(
            LotFactureAssuranceId::fromInt(4),
            LotStatus::CONFIRME,
        );

        $lot->unconfirm();

        self::assertTrue($lot->isEnvoye());
    }

    public function testSendRejectedWhenAlreadyEnvoye(): void
    {
        $lot = LotFactureAssurance::reconstitute(
            LotFactureAssuranceId::fromInt(5),
            LotStatus::ENVOYE,
        );

        $this->expectException(BillingDomainException::class);
        $this->expectExceptionMessage('Cannot send lot from status "envoye".');

        $lot->send();
    }

    public function testLegacyRecouvreNormalizesToRembourse(): void
    {
        $lot = LotFactureAssurance::reconstitute(
            LotFactureAssuranceId::fromInt(6),
            LotStatus::LEGACY_RECOUVRE,
        );

        self::assertSame(LotStatus::REMBOURSE, $lot->getStatusValue());
    }
}
