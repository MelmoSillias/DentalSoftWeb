<?php

namespace App\Tests\Unit\Scheduling\Domain\Model;

use App\Scheduling\Domain\Exception\SchedulingDomainException;
use App\Scheduling\Domain\Model\Rdv;
use App\Scheduling\Domain\ValueObject\RdvId;
use PHPUnit\Framework\TestCase;

final class RdvTest extends TestCase
{
    public function testValidateFromPending(): void
    {
        $rdv = Rdv::reconstitute(RdvId::fromInt(1), Rdv::STATUS_PENDING, 10, 3);

        $rdv->validate();

        self::assertTrue($rdv->isValidated());
        self::assertSame(Rdv::STATUS_VALIDATED, $rdv->getStatus());
    }

    public function testCancelFromPending(): void
    {
        $rdv = Rdv::reconstitute(RdvId::fromInt(2), Rdv::STATUS_PENDING, 10, 3);

        $rdv->cancel();

        self::assertTrue($rdv->isCancelled());
        self::assertSame(Rdv::STATUS_CANCELLED, $rdv->getStatus());
    }

    public function testCancelFromValidated(): void
    {
        $rdv = Rdv::reconstitute(RdvId::fromInt(3), Rdv::STATUS_VALIDATED, 10, 3);

        $rdv->cancel();

        self::assertTrue($rdv->isCancelled());
    }

    public function testReportFromPending(): void
    {
        $rdv = Rdv::reconstitute(RdvId::fromInt(4), Rdv::STATUS_PENDING, 10, 3);

        $rdv->report();

        self::assertTrue($rdv->isReported());
        self::assertSame(Rdv::STATUS_REPORTED, $rdv->getStatus());
    }

    public function testCannotValidateCancelled(): void
    {
        $rdv = Rdv::reconstitute(RdvId::fromInt(5), Rdv::STATUS_CANCELLED, 10, 3);

        $this->expectException(SchedulingDomainException::class);
        $rdv->validate();
    }

    public function testCannotCancelReported(): void
    {
        $rdv = Rdv::reconstitute(RdvId::fromInt(6), Rdv::STATUS_REPORTED, 10, 3);

        $this->expectException(SchedulingDomainException::class);
        $rdv->cancel();
    }

    public function testCannotReportValidated(): void
    {
        $rdv = Rdv::reconstitute(RdvId::fromInt(7), Rdv::STATUS_VALIDATED, 10, 3);

        $this->expectException(SchedulingDomainException::class);
        $rdv->report();
    }

    public function testCannotCancelTwice(): void
    {
        $rdv = Rdv::reconstitute(RdvId::fromInt(8), Rdv::STATUS_PENDING, 10, 3);
        $rdv->cancel();

        $this->expectException(SchedulingDomainException::class);
        $rdv->cancel();
    }

    public function testUnknownLegacyStatusCannotValidate(): void
    {
        $rdv = Rdv::reconstitute(RdvId::fromInt(9), 99, 10, 3);

        $this->expectException(SchedulingDomainException::class);
        $rdv->validate();
    }
}
