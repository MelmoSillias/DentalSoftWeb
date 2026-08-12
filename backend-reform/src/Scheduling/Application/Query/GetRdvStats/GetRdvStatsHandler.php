<?php

namespace App\Scheduling\Application\Query\GetRdvStats;

use App\Scheduling\Application\Port\RdvReadPort;
use App\Shared\Application\Bus\QueryHandler;
use DateTimeImmutable;

final class GetRdvStatsHandler implements QueryHandler
{
    public function __construct(private readonly RdvReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetRdvStatsQuery $query): array
    {
        if ($query->date !== null && $query->date !== '') {
            $date = $this->parseDate($query->date);
            if ($date === null) {
                return ['error' => 'Format de date invalide', 'status' => 400];
            }

            if ($query->medecinId !== null) {
                return $this->readPort->getStatsForMedecinDate($date, $query->medecinId);
            }

            return $this->readPort->getStatsForDate($date);
        }

        if ($query->start !== null && $query->end !== null) {
            $start = $this->parseDate($query->start);
            $end = $this->parseDate($query->end);
            if ($start === null || $end === null) {
                return ['error' => 'Format de date invalide', 'status' => 400];
            }

            return $this->readPort->getStatsForRange($start, $end);
        }

        return $this->readPort->getStatsForDate(new DateTimeImmutable('today'));
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $trimmed = substr($value, 0, 10);
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
