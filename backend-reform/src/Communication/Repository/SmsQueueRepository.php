<?php

namespace App\Communication\Repository;

use App\Communication\Entity\SmsQueue;
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

    public function findAppointmentRemindersForPatients(array $patientIds): array
    {
        $patientIds = array_values(array_filter(array_map('intval', $patientIds)));
        if ($patientIds === []) {
            return [];
        }

        return $this->createQueryBuilder('q')
            ->andWhere('q.patient IN (:patientIds)')
            ->andWhere('q.type = :type')
            ->setParameter('patientIds', $patientIds)
            ->setParameter('type', 'appointment reminder')
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentQueue(int $limit = 100, int $offset = 0, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('q')
            ->orderBy('q.createdAt', 'DESC')
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, min($limit, 200)));

        if (is_string($status) && $status !== '') {
            $qb
                ->andWhere('q.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}