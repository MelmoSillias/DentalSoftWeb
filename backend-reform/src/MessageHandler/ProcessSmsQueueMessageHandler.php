<?php

namespace App\MessageHandler;

use App\Message\ProcessSmsQueueMessage;
use App\Service\SmsService;
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
