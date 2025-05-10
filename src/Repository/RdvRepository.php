<?php

namespace App\Repository;

use App\Entity\Rdv;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rdv>
 */
class RdvRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rdv::class);
    }

    public function findRdvByDate(\DateTime $date): array
    {
        $targetDate = $date->setTime(0, 0, 0);  // Mettre l'heure à minuit

        $nextDay = (clone $targetDate)->modify('+1 day');

        return $this->createQueryBuilder('r')
            ->where('r.dateRdv >= :start')
            ->andWhere('r.dateRdv < :end')
            ->setParameter('start', $targetDate)
            ->setParameter('end', $nextDay)
            ->getQuery()
            ->getResult();
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.patient', 'p')
            ->addSelect('p')
            ->join('r.medecin', 'm')
            ->addSelect('m')
            ->join('r.salle', 's')
            ->addSelect('s')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Rdv[] Returns an array of Rdv objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Rdv
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
