<?php

namespace App\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Billing\Infrastructure\Persistence\Doctrine\Entity\FactureAssurance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FactureAssurance>
 */
class FactureAssuranceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactureAssurance::class);
    }
}
