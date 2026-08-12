<?php

namespace App\Focus\Application\Query\GetDashboardStats;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe;
use DateTimeImmutable;

final class GetDashboardStatsQuery
{
    public function __construct(
        public readonly string $role,
        public readonly string $type,
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly ?Employe $medecin = null,
    ) {
    }
}
