<?php

namespace App\Tests\Unit\CareDelivery\Domain\Model;

use App\CareDelivery\Domain\Exception\CareDeliveryDomainException;
use App\CareDelivery\Domain\Model\Ordonnance;
use App\CareDelivery\Domain\Model\OrdonnanceLigne;
use App\CareDelivery\Domain\ValueObject\OrdonnanceId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OrdonnanceTest extends TestCase
{
    public function testCreateRequiresAtLeastOneLine(): void
    {
        $this->expectException(CareDeliveryDomainException::class);
        $this->expectExceptionMessage('Ordonnance requires at least one line.');

        Ordonnance::create(10, []);
    }

    public function testCreateRejectsEmptyDesignation(): void
    {
        $this->expectException(CareDeliveryDomainException::class);
        $this->expectExceptionMessage('Ordonnance line designation cannot be empty.');

        Ordonnance::create(10, [new OrdonnanceLigne('  ')]);
    }

    public function testReplaceLinesRequiresLines(): void
    {
        $ordonnance = Ordonnance::reconstitute(
            OrdonnanceId::fromInt(1),
            10,
            new DateTimeImmutable(),
            [new OrdonnanceLigne('Amoxicilline')],
        );

        $this->expectException(CareDeliveryDomainException::class);
        $this->expectExceptionMessage('Ordonnance requires at least one line.');

        $ordonnance->replaceLines([]);
    }

    public function testReplaceLinesUpdatesAggregate(): void
    {
        $ordonnance = Ordonnance::create(10, [new OrdonnanceLigne('Amoxicilline')]);

        $ordonnance->replaceLines([
            new OrdonnanceLigne('Ibuprofene', '400mg', '2x/j', '5j', 10, 'apres repas'),
        ]);

        self::assertCount(1, $ordonnance->getLignes());
        self::assertSame('Ibuprofene', $ordonnance->getLignes()[0]->getDesignation());
    }
}
