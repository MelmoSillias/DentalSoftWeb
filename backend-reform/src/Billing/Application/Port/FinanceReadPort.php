<?php

namespace App\Billing\Application\Port;

interface FinanceReadPort
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listPaymentMethods(): array;

    /**
     * @return array<string, mixed>
     */
    public function getChartData(int $year): array;

    /**
     * @return array<string, mixed>
     */
    public function getMonthlyCrossTable(int $year, int $month, string $type = 'revenue'): array;

    /**
     * @return array<string, mixed>
     */
    public function getCrossTableDayOverview(string $date): array;

    /**
     * @return array{items: list<array<string, mixed>>, total: float}
     */
    public function listFixedCharges(): array;
}
