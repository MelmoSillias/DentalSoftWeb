<?php

namespace App\Billing\Repository;

use App\Billing\Entity\Transaction;
use App\Patient\Entity\Patient;
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

    public function findByDateRange(?\DateTimeInterface $from, ?\DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('t');
        if ($from) {
            $qb->andWhere('t.dateTransaction >= :from')
               ->setParameter('from', $from->format('Y-m-d') . ' 00:00:00');
        }
        if ($to) {
            $qb->andWhere('t.dateTransaction <= :to')
               ->setParameter('to', $to->format('Y-m-d') . ' 23:59:59');
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

    /** @return Transaction[] */
    public function findByPortalPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'pc')->addSelect('pc')
            ->leftJoin('t.facture', 'f')->addSelect('f')
            ->leftJoin('f.consultation', 'fc')->addSelect('fc')
            ->leftJoin('fc.patient', 'pf')->addSelect('pf')
            ->leftJoin('t.modeDePaiement', 'm')->addSelect('m')
            ->where('pc = :patient OR pf = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('t.dateTransaction', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPortalReceiptById(Patient $patient, int $transactionId): ?Transaction
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'pc')->addSelect('pc')
            ->leftJoin('t.facture', 'f')->addSelect('f')
            ->leftJoin('f.consultation', 'fc')->addSelect('fc')
            ->leftJoin('fc.patient', 'pf')->addSelect('pf')
            ->leftJoin('t.modeDePaiement', 'm')->addSelect('m')
            ->where('t.id = :id')
            ->andWhere('pc = :patient OR pf = :patient')
            ->setParameter('id', $transactionId)
            ->setParameter('patient', $patient)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}