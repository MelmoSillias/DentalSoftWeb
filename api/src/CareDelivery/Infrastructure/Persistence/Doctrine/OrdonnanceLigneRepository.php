<?php

namespace App\CareDelivery\Infrastructure\Persistence\Doctrine;

use App\CareDelivery\Domain\Entity\OrdonnanceLigne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrdonnanceLigne>
 */
class OrdonnanceLigneRepository extends ServiceEntityRepository implements \App\CareDelivery\Domain\Repository\OrdonnanceLigneRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrdonnanceLigne::class);
    }
}
