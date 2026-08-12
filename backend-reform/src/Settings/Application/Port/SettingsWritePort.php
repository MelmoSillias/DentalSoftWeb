<?php

namespace App\Settings\Application\Port;

interface SettingsWritePort
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function saveGeneralSettings(array $payload): array;
}
