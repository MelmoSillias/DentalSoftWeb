<?php

namespace App\Communication\Application\Command\MarkNotificationsRead;

use App\Communication\Application\Port\NotificationWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class MarkNotificationsReadHandler implements CommandHandler
{
    public function __construct(private readonly NotificationWritePort $writePort)
    {
    }

    public function __invoke(MarkNotificationsReadCommand $command): int
    {
        return $this->writePort->markAsRead($command->user, $command->ids);
    }
}
