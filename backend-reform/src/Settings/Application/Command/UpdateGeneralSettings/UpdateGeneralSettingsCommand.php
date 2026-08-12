<?php

namespace App\Settings\Application\Command\UpdateGeneralSettings;

final class UpdateGeneralSettingsCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public readonly array $payload)
    {
    }
}
