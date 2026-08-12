<?php

namespace App\CareDelivery\Application\Command\UpdateFactureLines;

use App\CareDelivery\Application\Port\ConsultationWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateFactureLinesHandler implements CommandHandler
{
    public function __construct(private readonly ConsultationWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateFactureLinesCommand $command): array
    {
        return $this->writePort->updateFactureLines(
            $command->consultationId,
            $command->lignes,
            $command->date,
            $command->time,
        );
    }
}
