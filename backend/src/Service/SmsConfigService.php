<?php

namespace App\Service;

use App\Entity\SmsProviderConfig;
use App\Repository\SmsProviderConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SmsConfigService
{
    public function __construct(
        private readonly SmsProviderConfigRepository $configRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CryptoService $cryptoService,
    ) {
    }

    public function getConfig(): SmsProviderConfig
    {
        return $this->configRepository->getMainConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicConfig(): array
    {
        $config = $this->getConfig();
        $encrypted = $config->getClientSecretEncrypted() ?? '';

        return [
            'provider' => $config->getProvider(),
            'enabled' => $config->isEnabled(),
            'clientId' => $config->getClientId(),
            'hasClientSecret' => $encrypted !== '',
            'clientSecretMasked' => $encrypted !== '' ? '********' : '',
            'senderName' => $config->getSenderName(),
            'baseUrl' => $config->getBaseUrl(),
            'oauthUrl' => $config->getOauthUrl(),
            'updatedAt' => $config->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveConfig(array $payload): array
    {
        $config = $this->getConfig();

        $config->setEnabled((bool) ($payload['enabled'] ?? $config->isEnabled()));
        $config->setClientId($this->sanitizeString($payload['clientId'] ?? $config->getClientId()));
        $config->setSenderName($this->sanitizeString($payload['senderName'] ?? $config->getSenderName()));

        $baseUrl = $this->sanitizeString($payload['baseUrl'] ?? $config->getBaseUrl()) ?: $config->getBaseUrl();
        $oauthUrl = $this->sanitizeString($payload['oauthUrl'] ?? $config->getOauthUrl()) ?: $config->getOauthUrl();

        $config->setBaseUrl($baseUrl);
        $config->setOauthUrl($oauthUrl);

        $rawSecret = $this->sanitizeString($payload['clientSecret'] ?? null);
        if ($rawSecret !== null && $rawSecret !== '') {
            $config->setClientSecretEncrypted($this->cryptoService->encrypt($rawSecret));
        }

        $config->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        return $this->getPublicConfig();
    }

    public function getClientSecret(SmsProviderConfig $config): ?string
    {
        return $this->cryptoService->decrypt($config->getClientSecretEncrypted());
    }

    /**
     * @return array{valid: bool, message?: string}
     */
    public function validateReadyConfig(?SmsProviderConfig $config = null): array
    {
        $config ??= $this->getConfig();

        if (!$config->isEnabled()) {
            return ['valid' => false, 'message' => 'Le module SMS est désactivé.'];
        }

        if (!$config->getClientId() || !$this->getClientSecret($config) || !$config->getSenderName()) {
            return ['valid' => false, 'message' => 'Configuration SMS incomplète (Client ID / Secret / Sender).'];
        }

        return ['valid' => true];
    }

    private function sanitizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return $value === null ? null : (string) $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
