<?php

namespace App\Communication\Infrastructure\Adapter;

use App\Communication\Application\Port\SmsReadPort;
use App\Communication\Service\SmsService;
use DateTimeImmutable;

final class LegacySmsReadAdapter implements SmsReadPort
{
    public function __construct(private readonly SmsService $smsService)
    {
    }

    public function stats(?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array
    {
        return $this->smsService->stats($from, $to);
    }

    public function listLogs(int $limit = 50, int $offset = 0): array
    {
        return $this->smsService->listLogs($limit, $offset);
    }
}
