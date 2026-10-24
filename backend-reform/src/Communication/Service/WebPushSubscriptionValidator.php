<?php

namespace App\Communication\Service;

final class WebPushSubscriptionValidator
{
    private const MAX_ENDPOINT_LENGTH = 2048;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{endpoint: string, p256dh: string, auth: string, contentEncoding: string}
     */
    public function validate(array $payload): array
    {
        $endpoint = $payload['endpoint'] ?? null;
        if (!is_string($endpoint) || !$this->isAllowedEndpoint($endpoint)) {
            throw new \InvalidArgumentException('Endpoint push refusé.');
        }

        $keys = $payload['keys'] ?? null;
        if (!is_array($keys)) {
            throw new \InvalidArgumentException('Clés de chiffrement manquantes.');
        }

        $p256dh = $keys['p256dh'] ?? null;
        $auth = $keys['auth'] ?? null;
        if (!is_string($p256dh) || strlen($this->decodeKey($p256dh)) !== 65) {
            throw new \InvalidArgumentException('Clé p256dh invalide.');
        }
        if (!is_string($auth) || strlen($this->decodeKey($auth)) !== 16) {
            throw new \InvalidArgumentException('Clé auth invalide.');
        }

        $contentEncoding = $payload['contentEncoding'] ?? 'aes128gcm';
        if (!is_string($contentEncoding) || !in_array($contentEncoding, ['aes128gcm', 'aesgcm'], true)) {
            throw new \InvalidArgumentException('Encodage de contenu invalide.');
        }

        return [
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'contentEncoding' => $contentEncoding,
        ];
    }

    public function isAllowedEndpoint(string $endpoint): bool
    {
        if (strlen($endpoint) > self::MAX_ENDPOINT_LENGTH) {
            return false;
        }

        $parts = parse_url($endpoint);
        if (!is_array($parts)) {
            return false;
        }

        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return $host === 'fcm.googleapis.com'
            || $host === 'updates.push.services.mozilla.com'
            || $host === 'web.push.apple.com'
            || $host === 'notify.windows.com'
            || str_ends_with($host, '.notify.windows.com');
    }

    public function sanitizeLink(?string $link): ?string
    {
        if ($link === null) {
            return null;
        }

        $link = trim($link);
        if ($link === '' || !str_starts_with($link, '/') || str_starts_with($link, '//')) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $link) === 1) {
            return null;
        }

        if (str_contains($link, '\\') || str_contains($link, "\n") || str_contains($link, "\r")) {
            return null;
        }

        return $link;
    }

    private function decodeKey(string $value): string
    {
        if (preg_match('#^[A-Za-z0-9_-]+={0,2}$#', $value) !== 1) {
            return '';
        }

        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
