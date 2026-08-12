<?php

namespace App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository;

use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\OrdonnanceLigne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrdonnanceLigne>
 */
class OrdonnanceLigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrdonnanceLigne::class);
    }
}
