<?php

namespace App\Focus\Infrastructure\Adapter;

use App\CareDelivery\Application\Port\ConsultationFocusPort;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\Focus\Service\FocusRealtimePublisher;

final class ConsultationFocusAdapter implements ConsultationFocusPort
{
    public function __construct(
        private readonly FocusRealtimePublisher $focusRealtimePublisher,
    ) {
    }

    public function publishConsultationRefresh(object $consultation, string $action): void
    {
        if (!$consultation instanceof Consultation) {
            return;
        }

        $this->focusRealtimePublisher->publishConsultationRefresh($consultation, $action);
    }
}
