<?php

namespace App\Scheduling\Application\Command\HandleRdvAction;

use App\Scheduling\Application\Port\RdvWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class HandleRdvActionHandler implements CommandHandler
{
    public function __construct(private readonly RdvWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(HandleRdvActionCommand $command): array
    {
        return $this->writePort->handleAction(
            $command->rdvId,
            $command->action,
            $command->payload,
            $command->actor,
        );
    }
}
