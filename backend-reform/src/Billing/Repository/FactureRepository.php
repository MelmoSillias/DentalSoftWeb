<?php

namespace App\Billing\Repository;

use App\Billing\Entity\Facture;
use App\Patient\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    /** @return Facture[] */
    public function findByPortalPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->where('p = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('f.dateFacture', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
