<?php

namespace App\CareDelivery\Application\Command\VerifyConsultationMedecinPassword;

final class VerifyConsultationMedecinPasswordCommand
{
    public function __construct(
        public readonly int $consultationId,
        public readonly string $password,
    ) {
    }
}
