<?php

namespace App\Scheduling\Repository;

use App\Scheduling\Entity\Rdv;
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
        $targetDate = $date->setTime(0, 0, 0);
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
}