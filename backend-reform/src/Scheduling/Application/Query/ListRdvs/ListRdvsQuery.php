<?php

namespace App\Scheduling\Application\Query\ListRdvs;

final class ListRdvsQuery
{
    public const MODE_DATE = 'date';
    public const MODE_RANGE = 'range';
    public const MODE_PENDING = 'pending';

    public function __construct(
        public readonly string $mode,
        public readonly ?string $date = null,
        public readonly ?string $start = null,
        public readonly ?string $end = null,
        public readonly ?int $medecinId = null,
        public readonly bool $excludeCancelled = false,
    ) {
    }
}
