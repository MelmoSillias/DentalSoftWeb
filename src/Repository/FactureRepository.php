<?php

namespace App\Repository;

use App\Entity\Facture;
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

    public function findUnpaid(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.statut = 0')
            ->leftJoin('f.consultation', 'c')
            ->addSelect('c')
            ->leftJoin('c.patient', 'p')
            ->addSelect('p')
            ->orderBy('f.dateEmission', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPaidByPeriod(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('f')
            ->andWhere('f.statut = 1')
            ->andWhere('f.payeLe BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->leftJoin('f.consultation', 'c')
            ->addSelect('c')
            ->leftJoin('c.patient', 'p')
            ->addSelect('p')
            ->orderBy('f.payeLe', 'DESC')
            ->getQuery()
            ->getResult();

        $total = array_reduce($result, function ($carry, $facture) {
            return $carry + $facture->getMontantTotal();
        }, 0);

        return [
            'factures' => $result,
            'total' => $total,
            'count' => count($result)
        ];
    }

    public function findPaidGroupedByMode(\DateTime $start, \DateTime $end): array
{
    $qb = $this->createQueryBuilder('f')
        ->select('m.id AS mode_id, m.libelle AS mode, SUM(f.montantTotal) AS total, COUNT(f.id) AS nombre')
        ->join('f.modeDePaiement', 'm')
        ->where('f.payeLe BETWEEN :start AND :end')
        ->andWhere('f.statut = 1')
        ->groupBy('m.id')
        ->orderBy('total', 'DESC')
        ->setParameter('start', $start)
        ->setParameter('end', $end);

    return $qb->getQuery()->getResult();
}

    
    //    /**
    //     * @return Facture[] Returns an array of Facture objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Facture
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
