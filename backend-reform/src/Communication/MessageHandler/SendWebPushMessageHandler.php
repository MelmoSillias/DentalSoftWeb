<?php

namespace App\Communication\MessageHandler;

use App\Communication\Message\SendWebPushMessage;
use App\Communication\Service\WebPushSender;
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
