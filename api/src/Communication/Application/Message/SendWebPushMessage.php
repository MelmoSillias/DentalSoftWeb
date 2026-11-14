<?php

namespace App\Communication\Application\Message;

final class SendWebPushMessage
{
    public function __construct(
        public readonly int $notificationId,
    ) {
    }
}
