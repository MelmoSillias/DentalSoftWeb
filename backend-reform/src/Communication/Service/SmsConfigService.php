<?php

namespace App\Communication\Service;

use App\Communication\Entity\SmsProviderConfig;
use App\Communication\Repository\SmsProviderConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SmsConfigService
{
    private const DEFAULT_PATIENT_PREFERENCE_BYPASS = [
        'patientCreated' => false,
        'receipt' => false,
        'ticket' => false,
        'invoice' => false,
        'appointmentReminder' => false,
        'unsubscribed' => false,
        'blacklisted' => false,
    ];

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
            'patientPreferenceBypass' => $this->sanitizePatientPreferenceBypass($config->getPatientPreferenceBypass()),
            'baseUrl' => $config->getBaseUrl(),
            'oauthUrl' => $config->getOauthUrl(),
            'webhookBaseUrl' => $config->getWebhookBaseUrl(),
            'callbackNotifyType' => $config->getCallbackNotifyType(),
            'updatedAt' => $config->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveConfig(array $payload): array
    {
        $config = $this->getConfig();
        $previousProvider = $config->getProvider();

        $provider = $this->sanitizeString($payload['provider'] ?? $config->getProvider()) ?: $config->getProvider();
        if (!in_array($provider, [SmsClientResolver::PROVIDER_ORANGE, SmsClientResolver::PROVIDER_AFRIKSMS], true)) {
            $provider = SmsClientResolver::PROVIDER_ORANGE;
        }

        $config->setProvider($provider);
        $config->setEnabled((bool) ($payload['enabled'] ?? $config->isEnabled()));
        $config->setClientId($this->sanitizeString($payload['clientId'] ?? $config->getClientId()));
        $config->setSenderAddress($this->sanitizeString($payload['senderAddress'] ?? $config->getSenderAddress()));
        $config->setSenderName($this->sanitizeString($payload['senderName'] ?? $config->getSenderName()));
        $config->setApprovedSenderNames($this->sanitizeSenderNameList($payload['approvedSenderNames'] ?? $config->getApprovedSenderNames()));
        $config->setPatientPreferenceBypass($this->sanitizePatientPreferenceBypass($payload['patientPreferenceBypass'] ?? $config->getPatientPreferenceBypass()));
        $config->setWebhookBaseUrl($this->sanitizeString($payload['webhookBaseUrl'] ?? $config->getWebhookBaseUrl()));

        if (array_key_exists('callbackNotifyType', $payload)) {
            $config->setCallbackNotifyType((int) $payload['callbackNotifyType']);
        }

        if ($provider !== $previousProvider) {
            $this->applyProviderDefaults($config, $provider);
        }

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

    public function buildAfrikSmsWebhookUrl(?SmsProviderConfig $config = null): ?string
    {
        $config ??= $this->getConfig();
        $baseUrl = trim((string) ($config->getWebhookBaseUrl() ?? ''));
        if ($baseUrl === '') {
            return null;
        }

        return rtrim($baseUrl, '/') . '/api/sms/webhooks/afriksms';
    }

    /**
     * @return array<string, bool>
     */
    public function getPatientPreferenceBypass(?SmsProviderConfig $config = null): array
    {
        $config ??= $this->getConfig();

        return $this->sanitizePatientPreferenceBypass($config->getPatientPreferenceBypass());
    }

    public function shouldBypassPatientPreference(string $key, ?SmsProviderConfig $config = null): bool
    {
        $bypass = $this->getPatientPreferenceBypass($config);

        return $bypass[$key] ?? false;
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

        if (!$config->getClientId() || !$this->getClientSecret($config)) {
            return ['valid' => false, 'message' => 'Configuration SMS incomplète (identifiants API manquants).'];
        }

        return match ($config->getProvider()) {
            SmsClientResolver::PROVIDER_AFRIKSMS => $this->validateAfrikSmsConfig($config),
            default => $this->validateOrangeConfig($config),
        };
    }

    /**
     * @return array{valid: bool, message?: string}
     */
    private function validateOrangeConfig(SmsProviderConfig $config): array
    {
        if (!$config->getSenderAddress()) {
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

    /**
     * @return array{valid: bool, message?: string}
     */
    private function validateAfrikSmsConfig(SmsProviderConfig $config): array
    {
        $senderId = trim((string) ($config->getSenderName() ?? ''));
        if ($senderId === '') {
            return ['valid' => false, 'message' => 'Configuration AfrikSms incomplète (SenderId requis).'];
        }

        if (!$this->looksLikeSenderName($senderId)) {
            return [
                'valid' => false,
                'message' => 'SenderId invalide. Utilisez 11 caractères maximum, alphanumériques et espaces uniquement.',
            ];
        }

        return ['valid' => true];
    }

    private function applyProviderDefaults(SmsProviderConfig $config, string $provider): void
    {
        if ($provider === SmsClientResolver::PROVIDER_AFRIKSMS) {
            $config->setBaseUrl(AfrikSmsClient::DEFAULT_BASE_URL);

            return;
        }

        $config->setBaseUrl('https://api.orange.com');
        $config->setOauthUrl('https://api.orange.com/oauth/v3/token');
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

    /**
     * @param mixed $value
     * @return array<string, bool>
     */
    private function sanitizePatientPreferenceBypass(mixed $value): array
    {
        $source = is_array($value) ? $value : [];
        $normalized = [];

        foreach (self::DEFAULT_PATIENT_PREFERENCE_BYPASS as $key => $default) {
            $normalized[$key] = (bool) ($source[$key] ?? $default);
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
