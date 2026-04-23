<?php

namespace App\Service;

use App\Communication\Entity\SmsProviderConfig;
use App\Communication\Repository\SmsProviderConfigRepository;
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
        $senderAddress = $config->getSenderAddress();
        $senderName = $config->getSenderName();

        if ($senderAddress === null && $this->looksLikePhoneAddress((string) $senderName)) {
            $senderAddress = $senderName;
            $senderName = null;
        }

        return [
            'provider' => $config->getProvider(),
            'enabled' => $config->isEnabled(),
            'clientId' => $config->getClientId(),
            'hasClientSecret' => $encrypted !== '',
            'clientSecretMasked' => $encrypted !== '' ? '********' : '',
            'senderAddress' => $senderAddress,
            'senderName' => $senderName,
            'approvedSenderNames' => $config->getApprovedSenderNames(),
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
        $config->setSenderAddress($this->sanitizeString($payload['senderAddress'] ?? $payload['senderName'] ?? $config->getSenderAddress()));
        $config->setSenderName($this->sanitizeString($payload['senderName'] ?? $config->getSenderName()));
        $config->setApprovedSenderNames($this->sanitizeSenderNameList($payload['approvedSenderNames'] ?? $config->getApprovedSenderNames()));

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

        if (!$config->getClientId() || !$this->getClientSecret($config) || !$config->getSenderAddress()) {
            return ['valid' => false, 'message' => 'Configuration SMS incomplète (Client ID / Secret / Sender).'];
        }

        if (!$this->looksLikePhoneAddress((string) $config->getSenderAddress())) {
            return [
                'valid' => false,
                'message' => 'Sender Address invalide. Utilisez une adresse technique au format international (ex: tel:+2230000 pour le Mali).',
            ];
        }

        if (!$this->looksLikeSenderName((string) ($config->getSenderName() ?? ''))) {
            return [
                'valid' => false,
                'message' => 'Sender Name invalide. Utilisez 11 caractères maximum, alphanumériques et espaces uniquement.',
            ];
        }

        return ['valid' => true];
    }

    private function looksLikeSenderName(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z0-9 ]{1,11}$/', $trimmed);
    }

    /**
     * @param mixed $values
     * @return array<int, string>
     */
    private function sanitizeSenderNameList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $item = trim((string) $value);
            if ($item === '' || !$this->looksLikeSenderName($item)) {
                continue;
            }

            if (!in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private function looksLikePhoneAddress(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, 'tel:')) {
            $trimmed = substr($trimmed, 4);
        }

        $normalized = preg_replace('/[^\d+]/', '', $trimmed) ?: '';
        if ($normalized === '') {
            return false;
        }

        if (str_starts_with($normalized, '+')) {
            return (bool) preg_match('/^\+[1-9]\d{5,14}$/', $normalized);
        }

        return (bool) preg_match('/^\d{6,15}$/', $normalized);
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
