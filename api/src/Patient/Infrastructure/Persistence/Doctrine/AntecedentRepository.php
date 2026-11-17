<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine;

use App\Patient\Domain\Entity\Antecedent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Antecedent>
 */
class AntecedentRepository extends ServiceEntityRepository implements \App\Patient\Domain\Repository\AntecedentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Antecedent::class);
    }
}