<?php

namespace App\Communication\Message;

final class SendWebPushMessage
{
    public function __construct(
        public readonly int $notificationId,
    ) {
    }
}
