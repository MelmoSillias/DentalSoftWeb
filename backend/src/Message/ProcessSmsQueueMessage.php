<?php

namespace App\Message;

final class ProcessSmsQueueMessage
{
    public function __construct(
        public readonly int $limit = 20,
    ) {
    }
}
