<?php

namespace App\Settings\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class InternetFeaturesGate
{
    public function __construct(
        #[Autowire('%env(bool:APP_INTERNET_FEATURES_ENABLED)%')]
        private bool $enabled = true,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function disabledMessage(): string
    {
        return 'Fonctionnalités Internet désactivées (mode local).';
    }
}
