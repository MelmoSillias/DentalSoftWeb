<?php

namespace App\Communication\Application\Command\QueueManualSms;

use DateTimeImmutable;

final class QueueManualSmsCommand
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly ?int $patientId = null,
        public readonly string $type = 'manual',
        public readonly string $source = 'manual',
        public readonly ?DateTimeImmutable $sendAt = null,
        public readonly ?array $metadata = null,
    ) {
    }
}
