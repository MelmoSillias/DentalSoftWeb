<?php

namespace App\CareDelivery\Application\Port;

interface ConsultationFocusPort
{
    public function publishConsultationRefresh(object $consultation, string $action): void;
}
