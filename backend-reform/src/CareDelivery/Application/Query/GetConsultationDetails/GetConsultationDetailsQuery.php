<?php

namespace App\CareDelivery\Application\Query\GetConsultationDetails;

final class GetConsultationDetailsQuery
{
    public const MODE_DATA = 'data';
    public const MODE_CONTEXT = 'context';

    public function __construct(
        public readonly int $consultationId,
        public readonly string $mode = self::MODE_DATA,
    ) {
    }
}
