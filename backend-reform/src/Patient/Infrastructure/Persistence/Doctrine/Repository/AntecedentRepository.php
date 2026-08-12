<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine\Repository;

use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Antecedent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Antecedent>
 */
class AntecedentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Antecedent::class);
    }
}