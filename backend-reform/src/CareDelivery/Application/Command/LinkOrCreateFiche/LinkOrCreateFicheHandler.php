<?php

namespace App\CareDelivery\Application\Command\LinkOrCreateFiche;

use App\CareDelivery\Application\Port\ConsultationWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class LinkOrCreateFicheHandler implements CommandHandler
{
    public function __construct(private readonly ConsultationWritePort $writePort)
    {
    }

    /**
     * @return array{ficheId: int, consultationId: int, created: bool}
     */
    public function __invoke(LinkOrCreateFicheCommand $command): array
    {
        return $this->writePort->linkOrCreateFiche(
            $command->consultationId,
            $command->ficheId,
            $command->user,
            $command->restrictToMedecin,
            $command->forceCreate,
            $command->allowDuplicate,
        );
    }
}
