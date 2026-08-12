<?php

namespace App\Tests\Unit\Billing\Infrastructure\Adapter;

use App\Billing\Infrastructure\Adapter\ConsultationBillingAdapter;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\ModeDePaiement;
use App\CareDelivery\Application\Port\ConsultationBillingPort;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\CareDelivery\Service\ConsultationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class ConsultationBillingAdapterTest extends TestCase
{
    public function testAdapterImplementsConsultationBillingPort(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $adapter = new ConsultationBillingAdapter($em);

        self::assertInstanceOf(ConsultationBillingPort::class, $adapter);
    }

    public function testConsultationServiceConstructorAcceptsBillingPort(): void
    {
        $ctor = (new ReflectionClass(ConsultationService::class))->getConstructor();
        self::assertNotNull($ctor);

        $paramTypes = [];
        foreach ($ctor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType) {
                $paramTypes[] = $type->getName();
            }
        }

        self::assertContains(ConsultationBillingPort::class, $paramTypes);
    }

    public function testCreateClassicTicketPaymentReturnsErrorForInvalidMode(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects(self::once())
            ->method('find')
            ->with(99)
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())
            ->method('getRepository')
            ->with(ModeDePaiement::class)
            ->willReturn($repo);
        $em->expects(self::never())->method('persist');

        $adapter = new ConsultationBillingAdapter($em);
        $consultation = $this->createMock(Consultation::class);

        $result = $adapter->createClassicTicketPayment(
            $consultation,
            5000.0,
            99,
            new DateTimeImmutable('2026-08-07T10:00:00+00:00'),
        );

        self::assertNull($result['paiement']);
        self::assertSame('Mode de paiement invalide.', $result['error']);
        self::assertSame(400, $result['status']);
    }
}
