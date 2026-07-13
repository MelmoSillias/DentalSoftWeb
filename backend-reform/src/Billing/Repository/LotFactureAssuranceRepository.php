<?php

namespace App\Billing\Repository;

use App\Billing\Entity\Assurance;
use App\Billing\Entity\LotFactureAssurance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LotFactureAssurance>
 */
class LotFactureAssuranceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotFactureAssurance::class);
    }

    public function findOpenLotForAssurance(Assurance $assurance): ?LotFactureAssurance
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.assurance = :assurance')
            ->andWhere('l.statut = :statut')
            ->setParameter('assurance', $assurance)
            ->setParameter('statut', 'ouvert')
            ->orderBy('l.dateCreation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<LotFactureAssurance> */
    public function findOpenLotsForAssurance(Assurance $assurance): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.assurance = :assurance')
            ->andWhere('l.statut = :statut')
            ->setParameter('assurance', $assurance)
            ->setParameter('statut', 'ouvert')
            ->orderBy('l.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestOpenLotForAssurance(Assurance $assurance): ?LotFactureAssurance
    {
        return $this->findOpenLotForAssurance($assurance);
    }
}
