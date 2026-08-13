<?php

namespace App\Communication\Mercure;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * Décorateur HubInterface : circuit breaker + publication best-effort.
 * Utilisé par le handler Messenger (et les appels sync explicites).
 */
final class ResilientMercureHub implements HubInterface
{
    public function __construct(
        private readonly HubInterface $inner,
        private readonly MercureCircuitBreaker $circuitBreaker,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getUrl(): string
    {
        return $this->inner->getUrl();
    }

    public function getPublicUrl(): string
    {
        return $this->inner->getPublicUrl();
    }

    public function getProvider(): TokenProviderInterface
    {
        return $this->inner->getProvider();
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->inner->getFactory();
    }

    public function publish(Update $update): string
    {
        if ($this->circuitBreaker->isOpen()) {
            $this->logger->notice('Publication Mercure ignoree : circuit ouvert.', [
                'topics' => $update->getTopics(),
                'type' => $update->getType(),
                'circuit' => $this->circuitBreaker->status(),
            ]);

            return '';
        }

        try {
            $result = $this->inner->publish($update);
            $this->circuitBreaker->recordSuccess();

            return $result;
        } catch (\Throwable $exception) {
            $this->circuitBreaker->recordFailure();
            $this->logger->warning('Echec de publication Mercure : circuit ouvert temporairement.', [
                'exception' => $exception,
                'topics' => $update->getTopics(),
                'type' => $update->getType(),
                'circuit' => $this->circuitBreaker->status(),
            ]);

            // Best-effort : ne fait pas echouer la requete / le message Messenger.
            return '';
        }
    }
}
