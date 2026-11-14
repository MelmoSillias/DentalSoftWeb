<?php

namespace App\CareDelivery\Application\Handler;

use App\CareDelivery\Application\Mapper\OrdonnanceMapper;
use App\CareDelivery\Application\Query\GetOrdonnance;
use App\CareDelivery\Domain\Entity\Ordonnance;
use Doctrine\ORM\EntityManagerInterface;

final class GetOrdonnanceHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrdonnanceMapper $mapper,
    ) {
    }

    public function __invoke(GetOrdonnance $query): ?array
    {
        $ordonnance = $this->em->getRepository(Ordonnance::class)->find($query->id);

        return $ordonnance ? $this->mapper->detail($ordonnance) : null;
    }
}
