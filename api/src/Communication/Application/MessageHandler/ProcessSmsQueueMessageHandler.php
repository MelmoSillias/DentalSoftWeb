<?php

namespace App\Communication\Application\MessageHandler;

use App\Communication\Application\Message\ProcessSmsQueueMessage;
use App\Communication\Application\Service\SmsService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessSmsQueueMessageHandler
{
    public function __construct(private readonly SmsService $smsService)
    {
    }

    public function __invoke(ProcessSmsQueueMessage $message): void
    {
        $this->smsService->processQueue($message->limit);
    }
}