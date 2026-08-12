<?php

namespace App\Scheduling\Application\Command\HandleRdvAction;

final class HandleRdvActionCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $rdvId,
        public readonly string $action,
        public readonly array $payload = [],
        public readonly ?object $actor = null,
    ) {
    }
}
