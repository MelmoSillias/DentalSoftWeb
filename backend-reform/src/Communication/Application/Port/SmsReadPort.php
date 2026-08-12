<?php

namespace App\Communication\Application\Port;

use DateTimeImmutable;

interface SmsReadPort
{
    /**
     * @return array<string, mixed>
     */
    public function stats(?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listLogs(int $limit = 50, int $offset = 0): array;
}
