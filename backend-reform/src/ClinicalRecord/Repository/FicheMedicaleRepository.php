<?php

namespace App\ClinicalRecord\Repository;

use App\ClinicalRecord\Entity\FicheMedicale;
use App\Patient\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FicheMedicaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheMedicale::class);
    }

    public function findLatestByPatient(?Patient $patient): ?FicheMedicale
    {
        if (!$patient?->getId()) {
            return null;
        }

        return $this->createQueryBuilder('f')
            ->andWhere('f.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('f.createdAt', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
