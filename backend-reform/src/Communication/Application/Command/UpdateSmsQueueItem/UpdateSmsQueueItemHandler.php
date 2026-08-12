<?php

namespace App\Communication\Application\Command\UpdateSmsQueueItem;

use App\Communication\Application\Port\SmsWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateSmsQueueItemHandler implements CommandHandler
{
    public function __construct(private readonly SmsWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateSmsQueueItemCommand $command): array
    {
        return $this->writePort->updateQueueItem($command->queueId, $command->action, $command->sendAt);
    }
}
