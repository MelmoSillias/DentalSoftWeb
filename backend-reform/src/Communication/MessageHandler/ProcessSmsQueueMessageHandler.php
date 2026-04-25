<?php

namespace App\Communication\MessageHandler;

use App\Communication\Message\ProcessSmsQueueMessage;
use App\Communication\Service\SmsService;
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