<?php

namespace App\Billing\Repository;

use App\Billing\Entity\Assurance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Assurance>
 */
class AssuranceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assurance::class);
    }

    public function findOneByCode(string $code): ?Assurance
    {
        return $this->createQueryBuilder('a')
            ->andWhere('UPPER(a.code) = :code')
            ->setParameter('code', strtoupper($code))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
