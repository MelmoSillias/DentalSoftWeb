<?php

namespace App\Billing\Application\Port;

use DateTimeInterface;

interface ClassicBillingPort
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listFacturesByPeriod(DateTimeInterface $start, DateTimeInterface $end): array;

    /**
     * @return array<string, mixed>|null
     */
    public function previewFactureDetail(int $id): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function previewFacture(int $id): ?array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function payerFacture(int $id, array $payload = []): array;

    /**
     * @return array<string, mixed>
     */
    public function resetFacturePayments(int $id): array;
}
