<?php

namespace App\Settings\Infrastructure\Adapter;

use App\CareDelivery\Application\Port\ConsultationSettingsPort;
use App\Settings\Service\GlobalSettingsService;

final class ConsultationSettingsAdapter implements ConsultationSettingsPort
{
    public function __construct(
        private readonly GlobalSettingsService $globalSettingsService,
    ) {
    }

    public function isMedecinRequiredOnCreation(): bool
    {
        return $this->globalSettingsService->isMedecinRequiredOnConsultationCreation();
    }

    public function getConsultationPrice(): float
    {
        return $this->globalSettingsService->getConsultationPrice();
    }

    public function isConsultationPriceEditableOnCreation(): bool
    {
        return $this->globalSettingsService->isConsultationPriceEditableOnCreation();
    }
}
