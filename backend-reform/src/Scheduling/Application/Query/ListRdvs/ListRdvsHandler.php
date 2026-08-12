<?php

namespace App\Scheduling\Application\Query\ListRdvs;

use App\Scheduling\Application\Port\RdvReadPort;
use App\Shared\Application\Bus\QueryHandler;
use DateTimeImmutable;

final class ListRdvsHandler implements QueryHandler
{
    public function __construct(private readonly RdvReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    public function __invoke(ListRdvsQuery $query): array
    {
        return match ($query->mode) {
            ListRdvsQuery::MODE_DATE => $this->listByDate($query),
            ListRdvsQuery::MODE_RANGE => $this->listByRange($query),
            ListRdvsQuery::MODE_PENDING => $this->listPending($query),
            default => ['error' => 'Mode de liste inconnu', 'status' => 400],
        };
    }

    /**
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    private function listByDate(ListRdvsQuery $query): array
    {
        if ($query->date === null || $query->date === '') {
            return ['error' => 'Format de date invalide', 'status' => 400];
        }

        $date = $this->parseStrictDate($query->date);
        if ($date === null) {
            return ['error' => 'Format de date invalide', 'status' => 400];
        }

        return $this->readPort->listByDate($date, $query->medecinId, $query->excludeCancelled);
    }

    /**
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    private function listByRange(ListRdvsQuery $query): array
    {
        if ($query->start === null || $query->end === null) {
            return ['error' => 'Plage de dates requise', 'status' => 400];
        }

        $start = $this->parseStrictDate(substr($query->start, 0, 10));
        $end = $this->parseStrictDate(substr($query->end, 0, 10));
        if ($start === null || $end === null) {
            return ['error' => 'Format de date invalide', 'status' => 400];
        }

        return $this->readPort->listByRange($start, $end, $query->medecinId, $query->excludeCancelled);
    }

    /**
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    private function listPending(ListRdvsQuery $query): array
    {
        if ($query->start === null || $query->end === null) {
            return ['error' => 'Plage de dates requise', 'status' => 400];
        }

        $start = $this->parseStrictDate($query->start);
        $end = $this->parseStrictDate($query->end);
        if ($start === null || $end === null) {
            return ['error' => 'Format de date invalide', 'status' => 400];
        }

        return $this->readPort->listPendingByRange($start, $end, $query->medecinId);
    }

    private function parseStrictDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();
        if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return null;
        }

        return $date;
    }
}
