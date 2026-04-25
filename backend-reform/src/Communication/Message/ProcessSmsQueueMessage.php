<?php

namespace App\Communication\Message;

final class ProcessSmsQueueMessage
{
    public function __construct(
        public readonly int $limit = 20,
    ) {
    }
}