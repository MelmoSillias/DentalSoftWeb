<?php

namespace App\Repository;

use App\Entity\Formulaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formulaire>
 */
class FormulaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formulaire::class);
    }

    public function findLatestVersion(string $code): ?Formulaire
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.code = :code')
            ->setParameter('code', $code)
            ->orderBy('f.version', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPublishedByCode(string $code): ?Formulaire
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.code = :code')
            ->andWhere('f.status = :status')
            ->setParameter('code', $code)
            ->setParameter('status', Formulaire::STATUS_PUBLISHED)
            ->orderBy('f.version', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}