<?php

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Injecte un HttpClient à timeout court dans le hub Mercure natif
 * sans écraser la définition du mercure-bundle.
 */
final class MercureHubHttpClientPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mercure.hub.default') || !$container->hasDefinition('mercure.http_client')) {
            return;
        }

        $hub = $container->getDefinition('mercure.hub.default');
        // Hub::__construct(..., ?HttpClientInterface $httpClient = null) → 5e argument (index 4)
        $hub->setArgument(4, new Reference('mercure.http_client'));
    }
}
