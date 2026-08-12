<?php

namespace App\Communication\Application\Command\TestSendSms;

final class TestSendSmsCommand
{
    public function __construct(
        public readonly string $phone,
        public readonly string $message,
    ) {
    }
}
