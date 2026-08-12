<?php

namespace App\Scheduling\Application\Command\CreateRdv;

use App\Scheduling\Application\Port\RdvWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class CreateRdvHandler implements CommandHandler
{
    public function __construct(private readonly RdvWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreateRdvCommand $command): array
    {
        return $this->writePort->createRdv($command->data, $command->actor);
    }
}
