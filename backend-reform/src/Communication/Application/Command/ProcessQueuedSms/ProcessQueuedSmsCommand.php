<?php

namespace App\Communication\Application\Command\ProcessQueuedSms;

final class ProcessQueuedSmsCommand
{
    public function __construct(public readonly int $limit = 20)
    {
    }
}
