<?php

namespace App\CareDelivery\Service;

use App\Settings\Service\GlobalSettingsService;

class ActAttributionResolver
{
    public function __construct(
        private GlobalSettingsService $globalSettingsService,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function resolveFromPayload(array $payload): string
    {
        $explicit = $payload['attribution'] ?? null;
        if (is_scalar($explicit) && (string) $explicit === 'cabinet') {
            return 'cabinet';
        }

        $type = trim((string) ($payload['type'] ?? $payload['designation'] ?? ''));
        if ($type === '') {
            return 'medecin';
        }

        return $this->resolveFromCatalog($type);
    }

    public function resolveFromCatalog(string $type): string
    {
        $label = trim($type);
        if ($label === '') {
            return 'medecin';
        }

        foreach ($this->globalSettingsService->getSoinsList() as $item) {
            if (($item['description'] ?? '') === $label) {
                return ($item['attribution'] ?? 'medecin') === 'cabinet' ? 'cabinet' : 'medecin';
            }
        }

        return 'medecin';
    }
}
