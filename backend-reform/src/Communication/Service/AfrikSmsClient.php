<?php

namespace App\Communication\Service;

use App\Communication\Contract\SmsClientInterface;
use App\Communication\Infrastructure\Persistence\Doctrine\Entity\SmsProviderConfig;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AfrikSmsClient implements SmsClientInterface
{
    public const DEFAULT_BASE_URL = 'https://api.afriksms.com/api/web/web_v1/outbounds';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SmsConfigService $smsConfigService,
    ) {
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            $config = $this->smsConfigService->getConfig();
            $check = $this->smsConfigService->validateReadyConfig($config);
            if (!$check['valid']) {
                return ['success' => false, 'message' => $check['message'] ?? 'Configuration SMS invalide'];
            }

            $balance = $this->fetchBalance($config);
            if (!($balance['success'] ?? false)) {
                return ['success' => false, 'message' => (string) ($balance['message'] ?? 'Connexion AfrikSms impossible.')];
            }

            return ['success' => true, 'message' => 'Connexion AfrikSms valide.'];
        } catch (\Throwable $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchProviderOverview(): array
    {
        try {
            $config = $this->smsConfigService->getConfig();
            $check = $this->smsConfigService->validateReadyConfig($config);
            if (!$check['valid']) {
                return [
                    'success' => false,
                    'message' => $check['message'] ?? 'Configuration SMS invalide',
                    'contracts' => [],
                ];
            }

            $balance = $this->fetchBalance($config);
            if (!($balance['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => (string) ($balance['message'] ?? 'Solde AfrikSms indisponible.'),
                    'contracts' => [],
                ];
            }

            $contracts = [];
            foreach ($balance['information'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $contracts[] = [
                    'id' => null,
                    'country' => $item['country'] ?? null,
                    'offerName' => 'AfrikSms',
                    'availableUnits' => isset($item['solde']) ? (int) $item['solde'] : null,
                    'status' => 'active',
                    'expirationDate' => null,
                    'type' => 'prepaid',
                    'isRecommended' => false,
                ];
            }

            if ($contracts !== []) {
                $contracts[0]['isRecommended'] = true;
            }

            return [
                'success' => true,
                'message' => 'Solde AfrikSms chargé.',
                'contracts' => $contracts,
            ];
        } catch (TransportExceptionInterface $exception) {
            return [
                'success' => false,
                'message' => 'Erreur réseau AfrikSms: ' . $exception->getMessage(),
                'contracts' => [],
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'contracts' => [],
            ];
        }
    }

    /**
     * @return array{success: bool, providerMessageId?: string|null, error?: string}
     */
    public function sendSms(string $phone, string $message, ?string $senderOverride = null): array
    {
        try {
            $config = $this->smsConfigService->getConfig();
            $check = $this->smsConfigService->validateReadyConfig($config);
            if (!$check['valid']) {
                return ['success' => false, 'error' => $check['message'] ?? 'Configuration SMS invalide'];
            }

            $credentials = $this->resolveCredentials($config);
            $senderId = $this->resolveSenderId($config, $senderOverride);
            $mobileNumber = $this->normalizeMobileNumber($phone);

            $response = $this->httpClient->request('POST', $this->endpoint($config, '/send'), [
                'body' => [
                    'ClientId' => $credentials['clientId'],
                    'ApiKey' => $credentials['apiKey'],
                    'SenderId' => $senderId,
                    'Message' => mb_substr($message, 0, 160),
                    'MobileNumbers' => $mobileNumber,
                ],
            ]);

            return $this->parseSingleSendResponse($response->getStatusCode(), $response->toArray(false));
        } catch (TransportExceptionInterface $exception) {
            return ['success' => false, 'error' => 'Erreur réseau AfrikSms: ' . $exception->getMessage()];
        } catch (\Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @param list<string> $phones
     * @return array{success: bool, results?: list<array<string, mixed>>, error?: string}
     */
    public function sendBulkSms(array $phones, string $message, ?string $senderOverride = null): array
    {
        try {
            $config = $this->smsConfigService->getConfig();
            $check = $this->smsConfigService->validateReadyConfig($config);
            if (!$check['valid']) {
                return ['success' => false, 'error' => $check['message'] ?? 'Configuration SMS invalide'];
            }

            $normalizedPhones = [];
            foreach ($phones as $phone) {
                if (!is_string($phone) || trim($phone) === '') {
                    continue;
                }
                $normalizedPhones[] = $this->normalizeMobileNumber($phone);
            }

            $normalizedPhones = array_values(array_unique($normalizedPhones));
            if ($normalizedPhones === []) {
                return ['success' => false, 'error' => 'Aucun numéro destinataire valide.'];
            }

            $credentials = $this->resolveCredentials($config);
            $senderId = $this->resolveSenderId($config, $senderOverride);

            $response = $this->httpClient->request('POST', $this->endpoint($config, '/send_multisms'), [
                'body' => [
                    'ClientId' => $credentials['clientId'],
                    'ApiKey' => $credentials['apiKey'],
                    'SenderId' => $senderId,
                    'Message' => mb_substr($message, 0, 160),
                    'MobileNumbers' => implode(',', $normalizedPhones),
                ],
            ]);

            return $this->parseBulkSendResponse($response->getStatusCode(), $response->toArray(false));
        } catch (TransportExceptionInterface $exception) {
            return ['success' => false, 'error' => 'Erreur réseau AfrikSms: ' . $exception->getMessage()];
        } catch (\Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @param list<array{phone: string, message: string}> $entries
     * @return array{success: bool, results?: list<array<string, mixed>>, error?: string}
     */
    public function sendPersonalizedBulkSms(array $entries, ?string $senderOverride = null): array
    {
        try {
            $config = $this->smsConfigService->getConfig();
            $check = $this->smsConfigService->validateReadyConfig($config);
            if (!$check['valid']) {
                return ['success' => false, 'error' => $check['message'] ?? 'Configuration SMS invalide'];
            }

            $payloadEntries = [];
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $phone = trim((string) ($entry['phone'] ?? ''));
                $message = trim((string) ($entry['message'] ?? ''));
                if ($phone === '' || $message === '') {
                    continue;
                }

                $payloadEntries[] = [
                    'MobileNumbers' => $this->normalizeMobileNumber($phone),
                    'Message' => mb_substr($message, 0, 160),
                ];
            }

            if ($payloadEntries === []) {
                return ['success' => false, 'error' => 'Aucun message personnalisé valide.'];
            }

            $credentials = $this->resolveCredentials($config);
            $senderId = $this->resolveSenderId($config, $senderOverride);

            $response = $this->httpClient->request('POST', $this->endpoint($config, '/send_customer_multisms'), [
                'body' => [
                    'ClientId' => $credentials['clientId'],
                    'ApiKey' => $credentials['apiKey'],
                    'SenderId' => $senderId,
                    'ContentMessage' => json_encode($payloadEntries, JSON_UNESCAPED_UNICODE),
                ],
            ]);

            return $this->parseBulkSendResponse($response->getStatusCode(), $response->toArray(false));
        } catch (TransportExceptionInterface $exception) {
            return ['success' => false, 'error' => 'Erreur réseau AfrikSms: ' . $exception->getMessage()];
        } catch (\Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{success: bool, providerMessageId?: string|null, error?: string}
     */
    public function sendSmsWithEmail(
        string $phone,
        string $message,
        ?string $email = null,
        ?string $subject = null,
        ?string $senderOverride = null,
    ): array {
        try {
            $config = $this->smsConfigService->getConfig();
            $check = $this->smsConfigService->validateReadyConfig($config);
            if (!$check['valid']) {
                return ['success' => false, 'error' => $check['message'] ?? 'Configuration SMS invalide'];
            }

            $credentials = $this->resolveCredentials($config);
            $senderId = $this->resolveSenderId($config, $senderOverride);
            $mobileNumber = $this->normalizeMobileNumber($phone);

            $body = [
                'ClientId' => $credentials['clientId'],
                'ApiKey' => $credentials['apiKey'],
                'SenderId' => $senderId,
                'Message' => mb_substr($message, 0, 160),
                'MobileNumbers' => $mobileNumber,
            ];

            if ($email !== null && trim($email) !== '') {
                $body['Email'] = trim($email);
            }

            if ($subject !== null && trim($subject) !== '') {
                $body['Subject'] = trim($subject);
            }

            $response = $this->httpClient->request('POST', $this->endpoint($config, '/send_emailsms'), [
                'body' => $body,
            ]);

            return $this->parseSingleSendResponse($response->getStatusCode(), $response->toArray(false));
        } catch (TransportExceptionInterface $exception) {
            return ['success' => false, 'error' => 'Erreur réseau AfrikSms: ' . $exception->getMessage()];
        } catch (\Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string, notifyURL?: string|null, typeNotification?: string|null}
     */
    public function configureCallbackUrl(string $notifyUrl, int $typeNotification = 2, ?SmsProviderConfig $config = null): array
    {
        try {
            $config ??= $this->smsConfigService->getConfig();
            $credentials = $this->resolveCredentials($config);

            $response = $this->httpClient->request('POST', $this->endpoint($config, '/callback_url'), [
                'body' => [
                    'ClientId' => $credentials['clientId'],
                    'ApiKey' => $credentials['apiKey'],
                    'notifyURL' => $notifyUrl,
                    'TypeNotification' => (string) max(1, min(2, $typeNotification)),
                ],
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($status >= 200 && $status < 300 && (int) ($data['code'] ?? 0) === 100) {
                return [
                    'success' => true,
                    'message' => (string) ($data['message'] ?? 'URL callback enregistrée.'),
                    'notifyURL' => isset($data['notifyURL']) ? (string) $data['notifyURL'] : $notifyUrl,
                    'typeNotification' => isset($data['typeNotification']) ? (string) $data['typeNotification'] : (string) $typeNotification,
                ];
            }

            return [
                'success' => false,
                'message' => $this->extractErrorMessage($data, $status),
            ];
        } catch (TransportExceptionInterface $exception) {
            return ['success' => false, 'message' => 'Erreur réseau AfrikSms: ' . $exception->getMessage()];
        } catch (\Throwable $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message?: string, information?: list<array<string, mixed>>}
     */
    public function fetchBalance(?SmsProviderConfig $config = null): array
    {
        $config ??= $this->smsConfigService->getConfig();
        $credentials = $this->resolveCredentials($config);

        $response = $this->httpClient->request('GET', $this->endpoint($config, '/solde'), [
            'query' => [
                'ClientId' => $credentials['clientId'],
                'ApiKey' => $credentials['apiKey'],
            ],
        ]);

        $status = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($status >= 200 && $status < 300 && (int) ($data['code'] ?? 0) === 100) {
            $information = [];
            foreach ($data['information'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $information[] = [
                    'country' => $item['country'] ?? null,
                    'solde' => isset($item['solde']) ? (int) $item['solde'] : null,
                ];
            }

            return [
                'success' => true,
                'message' => (string) ($data['message'] ?? 'Success operation'),
                'information' => $information,
            ];
        }

        return [
            'success' => false,
            'message' => $this->extractErrorMessage($data, $status),
            'information' => [],
        ];
    }

    /**
     * @return array{clientId: string, apiKey: string}
     */
    private function resolveCredentials(SmsProviderConfig $config): array
    {
        $clientId = trim((string) ($config->getClientId() ?? ''));
        $apiKey = trim((string) ($this->smsConfigService->getClientSecret($config) ?? ''));

        if ($clientId === '' || $apiKey === '') {
            throw new \RuntimeException('ClientId / ApiKey AfrikSms manquant.');
        }

        return ['clientId' => $clientId, 'apiKey' => $apiKey];
    }

    private function resolveSenderId(SmsProviderConfig $config, ?string $senderOverride = null): string
    {
        $senderId = trim($senderOverride ?: (string) ($config->getSenderName() ?? ''));
        if ($senderId === '') {
            throw new \RuntimeException('SenderId AfrikSms manquant.');
        }

        if (mb_strlen($senderId) > 11) {
            throw new \RuntimeException('SenderId AfrikSms invalide (11 caractères maximum).');
        }

        return $senderId;
    }

    private function normalizeMobileNumber(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, 'tel:')) {
            $phone = substr($phone, 4);
        }

        $normalized = preg_replace('/[^\d+]/', '', $phone) ?: '';
        if ($normalized === '') {
            throw new \RuntimeException('Numéro destinataire invalide.');
        }

        if (str_starts_with($normalized, '+')) {
            $normalized = ltrim($normalized, '+');
        } elseif (str_starts_with($normalized, '00')) {
            $normalized = ltrim(substr($normalized, 2), '0');
        } else {
            $digits = ltrim($normalized, '0');
            if (!str_starts_with($digits, '223')) {
                $digits = '223' . $digits;
            }
            $normalized = $digits;
        }

        return preg_replace('/\D/', '', $normalized) ?: '';
    }

    private function endpoint(SmsProviderConfig $config, string $path): string
    {
        return rtrim($config->getBaseUrl() ?: self::DEFAULT_BASE_URL, '/') . $path;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, providerMessageId?: string|null, error?: string}
     */
    private function parseSingleSendResponse(int $status, array $data): array
    {
        if ($status >= 200 && $status < 300 && (int) ($data['code'] ?? 0) === 100) {
            return [
                'success' => true,
                'providerMessageId' => isset($data['resourceId']) ? (string) $data['resourceId'] : null,
            ];
        }

        return ['success' => false, 'error' => $this->extractErrorMessage($data, $status)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, results?: list<array<string, mixed>>, error?: string}
     */
    private function parseBulkSendResponse(int $status, array $data): array
    {
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'error' => $this->extractErrorMessage($data, $status)];
        }

        $results = [];
        foreach ($data['data'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $phone = (string) ($item['phone'] ?? '');
            $itemCode = (int) ($item['code'] ?? 0);
            $results[] = [
                'phone' => $phone,
                'success' => $itemCode === 100,
                'providerMessageId' => isset($item['resourceId']) ? (string) $item['resourceId'] : null,
                'status' => isset($item['status']) ? (string) $item['status'] : null,
                'error' => $itemCode === 100 ? null : (string) ($item['status'] ?? 'Erreur AfrikSms'),
            ];
        }

        $globalSuccess = (int) ($data['code'] ?? 0) === 100;

        return [
            'success' => $globalSuccess,
            'results' => $results,
            'error' => $globalSuccess ? null : $this->extractErrorMessage($data, $status),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractErrorMessage(array $data, int $status): string
    {
        $message = isset($data['message']) && is_string($data['message']) ? trim($data['message']) : '';
        $code = isset($data['code']) ? (string) $data['code'] : '';

        if ($message !== '' && $code !== '' && !str_contains($message, $code)) {
            return $message . ' (code: ' . $code . ')';
        }

        if ($message !== '') {
            return $message;
        }

        return 'Erreur AfrikSms HTTP ' . $status;
    }
}
