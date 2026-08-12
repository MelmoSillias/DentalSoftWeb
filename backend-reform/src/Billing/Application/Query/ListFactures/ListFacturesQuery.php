<?php

namespace App\Billing\Application\Query\ListFactures;

use DateTimeInterface;

final class ListFacturesQuery
{
    public const SCOPE_ALL = 'all';
    public const SCOPE_CLASSIQUES = 'classiques';
    public const SCOPE_UNPAID = 'unpaid';

    public function __construct(
        public readonly string $scope,
        public readonly ?DateTimeInterface $start = null,
        public readonly ?DateTimeInterface $end = null,
    ) {
    }
}
