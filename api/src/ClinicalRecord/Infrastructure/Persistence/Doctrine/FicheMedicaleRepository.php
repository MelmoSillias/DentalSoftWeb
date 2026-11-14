<?php

namespace App\ClinicalRecord\Infrastructure\Persistence\Doctrine;

use App\ClinicalRecord\Domain\Entity\FicheMedicale;
use App\Patient\Domain\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FicheMedicaleRepository extends ServiceEntityRepository implements \App\ClinicalRecord\Domain\Repository\FicheMedicaleRepositoryInterface
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
