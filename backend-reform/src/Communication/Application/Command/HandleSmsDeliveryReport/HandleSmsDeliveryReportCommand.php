<?php

namespace App\Communication\Application\Command\HandleSmsDeliveryReport;

final class HandleSmsDeliveryReportCommand
{
    public function __construct(
        public readonly string $provider,
        public readonly string $resourceId,
        public readonly string $code,
        public readonly string $message,
    ) {
    }
}
