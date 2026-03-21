<?php

namespace App\Service;

use App\Entity\SmsProviderConfig;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OrangeSmsClient
{
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

            $token = $this->requestToken($config);

            return [
                'success' => $token !== '',
                'message' => $token !== '' ? 'Connexion Orange valide.' : 'Token Orange invalide.',
            ];
        } catch (\Throwable $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
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

            $token = $this->requestToken($config);
            $sender = $this->normalizeTelAddress($senderOverride ?: (string) $config->getSenderName());
            $recipient = $this->normalizeTelAddress($phone);

            $endpoint = sprintf(
                '%s/smsmessaging/v1/outbound/%s/requests',
                rtrim($config->getBaseUrl(), '/'),
                rawurlencode($sender)
            );

            $payload = [
                'outboundSMSMessageRequest' => [
                    'address' => $recipient,
                    'senderAddress' => $sender,
                    'senderName' => $senderOverride ?: $config->getSenderName(),
                    'outboundSMSTextMessage' => [
                        'message' => mb_substr($message, 0, 160),
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($status >= 200 && $status < 300) {
                $messageId = $data['outboundSMSMessageRequest']['resourceReference']['resourceURL']
                    ?? $data['outboundSMSMessageRequest']['resourceURL']
                    ?? null;

                return ['success' => true, 'providerMessageId' => $messageId];
            }

            $errorText = $data['requestError']['serviceException']['text']
                ?? $data['requestError']['policyException']['text']
                ?? ('Erreur Orange HTTP ' . $status);

            return ['success' => false, 'error' => $errorText];
        } catch (TransportExceptionInterface $exception) {
            return ['success' => false, 'error' => 'Erreur réseau Orange: ' . $exception->getMessage()];
        } catch (\Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    private function requestToken(SmsProviderConfig $config): string
    {
        $clientId = (string) $config->getClientId();
        $clientSecret = (string) $this->smsConfigService->getClientSecret($config);

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('Client ID/Secret SMS manquant.');
        }

        $response = $this->httpClient->request('POST', $config->getOauthUrl(), [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => 'grant_type=client_credentials',
        ]);

        $status = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($status < 200 || $status >= 300) {
            $error = $payload['error_description'] ?? $payload['error'] ?? ('HTTP ' . $status);
            throw new \RuntimeException('Échec OAuth Orange: ' . $error);
        }

        $token = $payload['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('Token OAuth Orange introuvable.');
        }

        return $token;
    }

    private function normalizeTelAddress(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, 'tel:')) {
            return $phone;
        }

        $normalized = preg_replace('/[^\d+]/', '', $phone) ?: '';
        if ($normalized === '') {
            throw new \RuntimeException('Numéro SMS invalide.');
        }

        if (!str_starts_with($normalized, '+')) {
            $normalized = '+' . ltrim($normalized, '0');
        }

        return 'tel:' . $normalized;
    }
}
