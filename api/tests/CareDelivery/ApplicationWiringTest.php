<?php

namespace App\Tests\CareDelivery;

use App\CareDelivery\Application\Handler\GetOrdonnanceHandler;
use App\CareDelivery\Application\Mapper\ConsultationInvoiceMapper;
use App\CareDelivery\Application\Mapper\OrdonnanceMapper;
use App\CareDelivery\Application\Policy\ConsultationInvoicePolicy;
use App\CareDelivery\Application\Query\GetOrdonnance;
use App\CareDelivery\Application\Service\ConsultationService;
use App\CareDelivery\Application\UseCase\SaveOrdonnance;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ApplicationWiringTest extends TestCase
{
    public function testExtractedServicesAutowireWithoutCyclesOrQueryServices(): void
    {
        $container = new ContainerBuilder();
        $services = [
            ConsultationService::class,
            GetOrdonnanceHandler::class,
            SaveOrdonnance::class,
            OrdonnanceMapper::class,
            ConsultationInvoiceMapper::class,
            ConsultationInvoicePolicy::class,
        ];
        foreach ($services as $service) {
            $container->register($service, $service)->setAutowired(true)->setPublic(true);
        }
        // Keep the legacy dependency graph external; compile the facade and new services together.
        foreach ($services as $service) {
            $constructor = (new \ReflectionClass($service))->getConstructor();
            foreach ($constructor?->getParameters() ?? [] as $parameter) {
                $dependency = $parameter->getType()->getName();
                if (!$container->hasDefinition($dependency)) {
                    $container->register($dependency, $dependency)->setSynthetic(true)->setPublic(true);
                }
            }
        }

        $container->compile();

        foreach ($services as $service) {
            self::assertTrue($container->has($service));
        }
        self::assertFalse($container->has(GetOrdonnance::class));
    }
}
