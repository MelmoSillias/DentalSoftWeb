<?php

namespace App\Scheduling\Application\Port;

interface RdvWritePort
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createRdv(array $data, ?object $actor = null): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function handleAction(int $rdvId, string $action, array $payload, ?object $actor = null): array;
}
