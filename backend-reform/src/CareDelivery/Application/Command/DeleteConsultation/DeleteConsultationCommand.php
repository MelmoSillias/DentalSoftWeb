<?php

namespace App\CareDelivery\Application\Command\DeleteConsultation;

final class DeleteConsultationCommand
{
    public function __construct(
        public readonly int $consultationId,
        public readonly ?object $actor = null,
    ) {
    }
}
