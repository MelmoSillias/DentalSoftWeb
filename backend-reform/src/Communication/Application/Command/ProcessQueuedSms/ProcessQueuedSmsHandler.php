<?php

namespace App\Communication\Application\Command\ProcessQueuedSms;

use App\Communication\Application\Port\SmsWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class ProcessQueuedSmsHandler implements CommandHandler
{
    public function __construct(private readonly SmsWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(ProcessQueuedSmsCommand $command): array
    {
        return $this->writePort->processQueue($command->limit);
    }
}
