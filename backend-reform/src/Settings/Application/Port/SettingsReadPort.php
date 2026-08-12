<?php

namespace App\Settings\Application\Port;

interface SettingsReadPort
{
    /**
     * @return array<string, mixed>
     */
    public function getGeneralSettings(): array;
}
