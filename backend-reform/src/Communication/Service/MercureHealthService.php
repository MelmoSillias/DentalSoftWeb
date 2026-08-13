<?php

namespace App\Communication\Service;

use App\Communication\Mercure\MercureCircuitBreaker;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MercureHealthService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MercureCircuitBreaker $circuitBreaker,
        private readonly string $mercureUrl,
        private readonly string $mercurePublicUrl,
        private readonly string $mercureJwtSecret,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnose(): array
    {
        $checks = [
            'mercureUrlConfigured' => trim($this->mercureUrl) !== '',
            'mercurePublicUrlConfigured' => trim($this->mercurePublicUrl) !== '',
            'mercureJwtConfigured' => trim($this->mercureJwtSecret) !== '',
            'publicUrlUsesHttps' => str_starts_with(strtolower(trim($this->mercurePublicUrl)), 'https://'),
            'internalPublishReachable' => false,
            'internalPublishStatus' => null,
            'internalPublishError' => null,
        ];

        if (!$checks['mercureUrlConfigured']) {
            return $checks + ['status' => 'misconfigured'];
        }

        try {
            $response = $this->httpClient->request('GET', $this->mercureUrl, [
                'timeout' => 5,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
            $checks['internalPublishReachable'] = $status < 500;
            $checks['internalPublishStatus'] = $status;
        } catch (\Throwable $exception) {
            $checks['internalPublishError'] = $exception->getMessage();
        }

        $status = 'ok';
        if (!$checks['mercurePublicUrlConfigured'] || !$checks['mercureJwtConfigured']) {
            $status = 'misconfigured';
        } elseif (!$checks['internalPublishReachable']) {
            $status = 'unreachable';
        } elseif ($this->circuitBreaker->isOpen()) {
            $status = 'circuit_open';
        } elseif (!$checks['publicUrlUsesHttps']) {
            $status = 'warning';
        }

        return $checks + [
            'status' => $status,
            'mercureUrl' => $this->mercureUrl,
            'mercurePublicUrl' => $this->mercurePublicUrl,
            'circuit' => $this->circuitBreaker->status(),
        ];
    }
}
