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
        $qb->andWhere('t.dateTransaction >= :from')
           ->setParameter('from', $from->format('Y-m-d').' 00:00:00');
    }
    if ($to) {
        $qb->andWhere('t.dateTransaction <= :to')
           ->setParameter('to', $to->format('Y-m-d').' 23:59:59');
    }
    return $qb->getQuery()->getResult();
}

public function findValidatedBetweenByTypes(\DateTimeInterface $from, \DateTimeInterface $to, array $types = []): array
{
    $qb = $this->createQueryBuilder('t')
        ->andWhere('t.validationStatus = :status')
        ->andWhere('t.validatedAt IS NOT NULL')
        ->andWhere('t.validatedAt BETWEEN :from AND :to')
        ->setParameter('status', 'validated')
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->orderBy('t.validatedAt', 'ASC');

    if ($types !== []) {
        $qb->andWhere('t.type IN (:types)')
            ->setParameter('types', $types);
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
