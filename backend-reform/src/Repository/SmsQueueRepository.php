<?php

namespace App\Repository;

use App\Entity\SmsQueue;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SmsQueue>
 */
class SmsQueueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmsQueue::class);
    }

    /**
     * @return list<SmsQueue>
     */
    public function findProcessable(int $limit = 20): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.status IN (:statuses)')
            ->andWhere('q.retryCount < :maxRetries')
            ->andWhere('q.sendAt IS NULL OR q.sendAt <= :now')
            ->setParameter('statuses', [SmsQueue::STATUS_PENDING, SmsQueue::STATUS_FAILED])
            ->setParameter('maxRetries', 3)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('q.createdAt', 'ASC')
            ->setMaxResults(max(1, min($limit, 200)))
            ->getQuery()
            ->getResult();
    }
}
