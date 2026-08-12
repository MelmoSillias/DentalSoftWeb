<?php

namespace App\Tests\Unit\CareDelivery\Application\Port;

use App\CareDelivery\Application\Port\ConsultationBillingPort;
use App\CareDelivery\Application\Port\ConsultationClinicalRecordPort;
use App\CareDelivery\Application\Port\ConsultationFocusPort;
use App\CareDelivery\Application\Port\ConsultationNotificationPort;
use App\CareDelivery\Application\Port\ConsultationPatientPort;
use App\CareDelivery\Application\Port\ConsultationSettingsPort;
use App\CareDelivery\Application\Port\ConsultationStaffPort;
use App\CareDelivery\Infrastructure\Adapter\ConsultationNotificationAdapter;
use App\CareDelivery\Infrastructure\Adapter\ConsultationStaffAdapter;
use App\CareDelivery\Service\ConsultationService;
use App\ClinicalRecord\Infrastructure\Adapter\ConsultationClinicalRecordAdapter;
use App\Focus\Infrastructure\Adapter\ConsultationFocusAdapter;
use App\Patient\Infrastructure\Adapter\ConsultationPatientAdapter;
use App\Settings\Infrastructure\Adapter\ConsultationSettingsAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class ConsultationOutboundPortsTest extends TestCase
{
    public function testConsultationServiceConstructorAcceptsOutboundPorts(): void
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
        self::assertContains(ConsultationFocusPort::class, $paramTypes);
        self::assertContains(ConsultationNotificationPort::class, $paramTypes);
        self::assertContains(ConsultationSettingsPort::class, $paramTypes);
        self::assertContains(ConsultationStaffPort::class, $paramTypes);
        self::assertContains(ConsultationClinicalRecordPort::class, $paramTypes);
        self::assertContains(ConsultationPatientPort::class, $paramTypes);
    }

    public function testAdaptersImplementOutboundPorts(): void
    {
        self::assertTrue(is_a(ConsultationFocusAdapter::class, ConsultationFocusPort::class, true));
        self::assertTrue(is_a(ConsultationNotificationAdapter::class, ConsultationNotificationPort::class, true));
        self::assertTrue(is_a(ConsultationSettingsAdapter::class, ConsultationSettingsPort::class, true));
        self::assertTrue(is_a(ConsultationStaffAdapter::class, ConsultationStaffPort::class, true));
        self::assertTrue(is_a(ConsultationClinicalRecordAdapter::class, ConsultationClinicalRecordPort::class, true));
        self::assertTrue(is_a(ConsultationPatientAdapter::class, ConsultationPatientPort::class, true));
    }
}
