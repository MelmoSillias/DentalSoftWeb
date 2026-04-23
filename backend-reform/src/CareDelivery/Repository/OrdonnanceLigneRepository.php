<?php

namespace App\CareDelivery\Repository;

use App\CareDelivery\Entity\OrdonnanceLigne;
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
