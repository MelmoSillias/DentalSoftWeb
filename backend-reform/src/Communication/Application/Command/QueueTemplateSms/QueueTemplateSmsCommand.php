<?php

namespace App\Communication\Application\Command\QueueTemplateSms;

use DateTimeImmutable;

final class QueueTemplateSmsCommand
{
    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public readonly int $patientId,
        public readonly string $templateCode,
        public readonly array $variables,
        public readonly string $source = 'automation',
        public readonly ?DateTimeImmutable $sendAt = null,
        public readonly ?array $metadata = null,
    ) {
    }
}
