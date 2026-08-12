<?php

namespace App\CareDelivery\Application\Command\VerifyConsultationMedecinPassword;

use App\CareDelivery\Application\Port\ConsultationWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class VerifyConsultationMedecinPasswordHandler implements CommandHandler
{
    public function __construct(private readonly ConsultationWritePort $writePort)
    {
    }

    public function __invoke(VerifyConsultationMedecinPasswordCommand $command): bool
    {
        return $this->writePort->verifyMedecinPassword(
            $command->consultationId,
            $command->password,
        );
    }
}
