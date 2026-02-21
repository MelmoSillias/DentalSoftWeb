<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
 * Récupère toutes les transactions entre $from et $to (ou toutes si dates nulles).
 */
public function findByDateRange(?\DateTimeInterface $from, ?\DateTimeInterface $to): array
{
    $qb = $this->createQueryBuilder('t');
    if ($from) {
        $qb->andWhere('t.date >= :from')
           ->setParameter('from', $from->format('Y-m-d').' 00:00:00');
    }
    if ($to) {
        $qb->andWhere('t.date <= :to')
           ->setParameter('to', $to->format('Y-m-d').' 23:59:59');
    }
    return $qb->getQuery()->getResult();
}


//    /**
//     * @return Transaction[] Returns an array of Transaction objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Transaction
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
