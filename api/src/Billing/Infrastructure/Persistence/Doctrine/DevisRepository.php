<?php

namespace App\Billing\Infrastructure\Persistence\Doctrine;

use App\Billing\Domain\Entity\Devis;
use App\Patient\Domain\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DevisRepository extends ServiceEntityRepository implements \App\Billing\Domain\Repository\DevisRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Devis::class);
    }

    /** @return Devis[] */
    public function findByPortalPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->where('p = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('d.date', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}