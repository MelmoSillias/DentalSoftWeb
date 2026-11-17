<?php

namespace App\CareDelivery\Infrastructure\Persistence\Doctrine;

use App\CareDelivery\Domain\Entity\Ordonnance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ordonnance>
 */
class OrdonnanceRepository extends ServiceEntityRepository implements \App\CareDelivery\Domain\Repository\OrdonnanceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ordonnance::class);
    }
}
