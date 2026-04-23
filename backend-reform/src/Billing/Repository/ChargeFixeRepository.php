<?php

namespace App\Billing\Repository;

use App\Billing\Entity\ChargeFixe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChargeFixe>
 */
class ChargeFixeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChargeFixe::class);
    }
}