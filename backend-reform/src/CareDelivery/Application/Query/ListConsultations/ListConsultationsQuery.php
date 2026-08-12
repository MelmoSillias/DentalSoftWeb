<?php

namespace App\CareDelivery\Application\Query\ListConsultations;

final class ListConsultationsQuery
{
    public const SCOPE_PENDING = 'pending';
    public const SCOPE_CLOSED = 'closed';
    public const SCOPE_DAY = 'day';
    public const SCOPE_RECEPTION_FOCUS = 'reception_focus';

    public function __construct(
        public readonly string $scope,
        public readonly ?object $user = null,
        public readonly bool $restrictToMedecin = false,
        public readonly ?string $date = null,
    ) {
    }
}
