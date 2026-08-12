<?php

namespace App\Communication\Application\Command\HandleSmsDeliveryReport;

use App\Communication\Application\Port\SmsWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class HandleSmsDeliveryReportHandler implements CommandHandler
{
    public function __construct(private readonly SmsWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(HandleSmsDeliveryReportCommand $command): array
    {
        return $this->writePort->handleDeliveryReport(
            $command->provider,
            $command->resourceId,
            $command->code,
            $command->message,
        );
    }
}
