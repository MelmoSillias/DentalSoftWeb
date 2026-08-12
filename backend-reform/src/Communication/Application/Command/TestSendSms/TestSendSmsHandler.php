<?php

namespace App\Communication\Application\Command\TestSendSms;

use App\Communication\Application\Port\SmsWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class TestSendSmsHandler implements CommandHandler
{
    public function __construct(private readonly SmsWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(TestSendSmsCommand $command): array
    {
        return $this->writePort->testSend($command->phone, $command->message);
    }
}
