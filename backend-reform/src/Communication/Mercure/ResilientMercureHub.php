<?php

namespace App\Communication\Mercure;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * Decorateur HubInterface : circuit breaker + publication best-effort.
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
            $this->circuitBreaker->recordDropped();
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
            if ($this->shouldOpenCircuit($exception)) {
                $this->circuitBreaker->recordFailure();
            }

            $this->circuitBreaker->recordDropped();
            $this->logger->warning('Echec de publication Mercure.', [
                'exception' => $exception,
                'topics' => $update->getTopics(),
                'type' => $update->getType(),
                'circuit' => $this->circuitBreaker->status(),
                'opensCircuit' => $this->shouldOpenCircuit($exception),
            ]);

            return '';
        }
    }

    private function shouldOpenCircuit(\Throwable $exception): bool
    {
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getResponse()->getStatusCode();

            return $statusCode >= 500 || $statusCode === 429;
        }

        return true;
    }
}
