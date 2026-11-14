<?php

namespace App\Inventory\Application\Query;

final readonly class FetchStocksQuery
{
    public function __construct(
        public ?int $consumableId = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {
    }
}
