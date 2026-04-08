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
            $sender = $this->normalizeTelAddress($senderOverride ?: (string) $config->getSenderName(), 'Sender Address');
            $recipient = $this->normalizeTelAddress($phone, 'Numéro destinataire');

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

            $errorText = $this->extractOrangeError($data, $status);

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

    private function normalizeTelAddress(string $phone, string $fieldLabel = 'Numéro SMS'): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, 'tel:')) {
            return $phone;
        }

        $normalized = preg_replace('/[^\d+]/', '', $phone) ?: '';
        if ($normalized === '') {
            throw new \RuntimeException($fieldLabel . ' invalide.');
        }

        if (!str_starts_with($normalized, '+')) {
            $normalized = '+' . ltrim($normalized, '0');
        }

        return 'tel:' . $normalized;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractOrangeError(array $data, int $status): string
    {
        $requestError = $data['requestError'] ?? null;
        if (!is_array($requestError)) {
            return 'Erreur Orange HTTP ' . $status;
        }

        $service = $requestError['serviceException'] ?? null;
        if (is_array($service)) {
            return $this->formatOrangeException($service, $status);
        }

        $policy = $requestError['policyException'] ?? null;
        if (is_array($policy)) {
            return $this->formatOrangeException($policy, $status);
        }

        return 'Erreur Orange HTTP ' . $status;
    }

    /**
     * @param array<string, mixed> $exception
     */
    private function formatOrangeException(array $exception, int $status): string
    {
        $text = isset($exception['text']) && is_string($exception['text']) ? trim($exception['text']) : '';
        $messageId = isset($exception['messageId']) && is_string($exception['messageId']) ? trim($exception['messageId']) : '';

        $variables = $exception['variables'] ?? null;
        if ($text !== '' && is_array($variables)) {
            $i = 1;
            foreach ($variables as $value) {
                if (!is_scalar($value)) {
                    $i++;
                    continue;
                }

                $text = str_replace('%' . $i, (string) $value, $text);
                $i++;
            }
        }

        if ($text === '' && $messageId === '') {
            return 'Erreur Orange HTTP ' . $status;
        }

        if ($text === '') {
            return 'Erreur Orange (code: ' . $messageId . ')';
        }

        // Orange often wraps account-level authorization failures as SVC0001 + AAS SVC0002.
        // Provide a direct, actionable explanation for users.
        $normalizedText = strtoupper($text);
        if (str_contains($normalizedText, 'AAS ERROR SVC0002') || $messageId === 'SVC0002') {
            return 'Accès API Orange refusé (SVC0002). Vérifiez que votre application est bien abonnée à SMS Mali (sms-ml), que le sender est autorisé sur votre contrat, et que les permissions de production/sandbox sont actives.';
        }

        if ($messageId !== '' && !str_contains($text, $messageId)) {
            return $text . ' (code: ' . $messageId . ')';
        }

        return $text;
    }
}
