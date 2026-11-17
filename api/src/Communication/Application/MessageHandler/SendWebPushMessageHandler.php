<?php

namespace App\Communication\Application\MessageHandler;

use App\Communication\Application\Message\SendWebPushMessage;
use App\Communication\Infrastructure\Integration\WebPushSender;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendWebPushMessageHandler
{
    public function __construct(private readonly WebPushSender $webPushSender)
    {
    }

    public function __invoke(SendWebPushMessage $message): void
    {
        $this->webPushSender->send($message->notificationId);
    }
}
