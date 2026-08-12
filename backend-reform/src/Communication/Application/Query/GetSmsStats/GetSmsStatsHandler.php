<?php

namespace App\Communication\Application\Query\GetSmsStats;

use App\Communication\Application\Port\SmsReadPort;
use App\Shared\Application\Bus\QueryHandler;
use DateTimeImmutable;

final class GetSmsStatsHandler implements QueryHandler
{
    public function __construct(private readonly SmsReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetSmsStatsQuery $query): array
    {
        $from = $query->from ? new DateTimeImmutable($query->from) : null;
        $to = $query->to ? new DateTimeImmutable($query->to) : null;

        return $this->readPort->stats($from, $to);
    }
}
