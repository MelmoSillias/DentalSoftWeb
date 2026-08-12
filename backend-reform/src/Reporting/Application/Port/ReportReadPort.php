<?php

namespace App\Reporting\Application\Port;

interface ReportReadPort
{
    /**
     * @return array<string, mixed>
     */
    public function getReportsData(
        string $period,
        ?string $customStart = null,
        ?string $customEnd = null,
        ?string $employeeId = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function globalStats(?string $from, ?string $to): array;

    /**
     * @return array<string, mixed>
     */
    public function globalPatients(): array;

    /**
     * @return array<string, mixed>
     */
    public function getReceptionStats(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
