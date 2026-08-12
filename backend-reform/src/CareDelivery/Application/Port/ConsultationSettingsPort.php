<?php

namespace App\CareDelivery\Application\Port;

interface ConsultationSettingsPort
{
    public function isMedecinRequiredOnCreation(): bool;

    public function getConsultationPrice(): float;

    public function isConsultationPriceEditableOnCreation(): bool;
}
