<?php

namespace App\Scheduling\Application\Port;

use DateTimeInterface;

interface RdvReadPort
{
    /**
     * @return array<string, mixed>
     */
    public function getStatsForDate(DateTimeInterface $date): array;

    /**
     * @return array<string, mixed>
     */
    public function getStatsForRange(DateTimeInterface $start, DateTimeInterface $end): array;

    /**
     * @return array<string, mixed>
     */
    public function getStatsForMedecinDate(DateTimeInterface $date, int $medecinId): array;

    /**
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    public function listByDate(DateTimeInterface $date, ?int $medecinId = null, bool $excludeCancelled = false): array;

    /**
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    public function listByRange(
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?int $medecinId = null,
        bool $excludeCancelled = false,
    ): array;

    /**
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    public function listPendingByRange(
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?int $medecinId = null,
    ): array;
}
