<?php

namespace App\Tests\Unit\IdentityAccess\Domain\Model;

use App\IdentityAccess\Domain\Exception\IdentityAccessDomainException;
use App\IdentityAccess\Domain\Model\Employe;
use App\IdentityAccess\Domain\ValueObject\EmployeId;
use PHPUnit\Framework\TestCase;

final class EmployeTest extends TestCase
{
    public function testCreateAndRename(): void
    {
        $employe = Employe::create('Doe', 'Jane', 'Assistante', 'Staff');

        self::assertNull($employe->getId());
        self::assertSame('Doe', $employe->getNom());
        self::assertSame('Jane', $employe->getPrenom());
        self::assertSame('Jane Doe', $employe->getFullName());

        $employe->rename('Smith', 'Jane');
        self::assertSame('Smith', $employe->getNom());
        self::assertSame('Jane Smith', $employe->getFullName());
    }

    public function testReconstituteAndAssignId(): void
    {
        $employe = Employe::reconstitute(
            EmployeId::fromInt(5),
            'Martin',
            'Paul',
            'Chirurgien dentiste',
            'Medecin',
        );

        self::assertSame(5, $employe->requireId()->toInt());
        self::assertSame('Medecin', $employe->getType());
        self::assertSame('Chirurgien dentiste', $employe->getFonction());

        $this->expectException(IdentityAccessDomainException::class);
        $employe->assignId(EmployeId::fromInt(6));
    }

    public function testCreateRejectsEmptyNom(): void
    {
        $this->expectException(IdentityAccessDomainException::class);
        Employe::create('  ', 'Jane', 'Assistante', 'Staff');
    }
}
