<?php

namespace App\Communication\Application\Command\SaveSmsTemplates;

use App\Communication\Application\Port\SmsWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class SaveSmsTemplatesHandler implements CommandHandler
{
    public function __construct(private readonly SmsWritePort $writePort)
    {
    }

    /**
     * @return array{success: true}
     */
    public function __invoke(SaveSmsTemplatesCommand $command): array
    {
        $this->writePort->saveTemplates($command->templates);

        return ['success' => true];
    }
}
