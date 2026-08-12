<?php

namespace App\Communication\Application\Command\QueueTemplateSms;

use App\Communication\Application\Port\SmsWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class QueueTemplateSmsHandler implements CommandHandler
{
    public function __construct(private readonly SmsWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(QueueTemplateSmsCommand $command): array
    {
        return $this->writePort->queueTemplateForPatient(
            $command->patientId,
            $command->templateCode,
            $command->variables,
            $command->source,
            $command->sendAt,
            $command->metadata,
        );
    }
}
