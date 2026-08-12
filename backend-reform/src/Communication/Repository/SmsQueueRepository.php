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
            ->andWhere('q.type IN (:types)')
            ->setParameter('patientIds', $patientIds)
            ->setParameter('types', ['appointment reminder', 'rappel de rdv'])
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<SmsQueue>
     */
    public function findByRdvMetadata(int $rdvId, ?array $statuses = null): array
    {
        if ($rdvId <= 0) {
            return [];
        }

        $qb = $this->createQueryBuilder('q')
            ->andWhere('q.type IN (:types)')
            ->setParameter('types', ['appointment reminder', 'rappel de rdv', 'appointment change'])
            ->orderBy('q.createdAt', 'DESC');

        if (is_array($statuses) && $statuses !== []) {
            $qb
                ->andWhere('q.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        $items = $qb->getQuery()->getResult();

        return array_values(array_filter($items, static function (SmsQueue $item) use ($rdvId): bool {
            $metadata = $item->getMetadata() ?? [];

            return (int) ($metadata['rdvId'] ?? 0) === $rdvId;
        }));
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

    /**
     * @return array{pendingDue: int, pendingScheduled: int, failedDue: int, failedScheduled: int, failedExhausted: int, sending: int, sent: int, cancelled: int, nextScheduledAt: ?DateTimeImmutable}
     */
    public function getProcessingSnapshot(): array
    {
        $now = new DateTimeImmutable();

        $countStatus = function (string $status, ?bool $due = null, ?bool $retryEligible = null) use ($now): int {
            $qb = $this->createQueryBuilder('q')
                ->select('COUNT(q.id)')
                ->andWhere('q.status = :status')
                ->setParameter('status', $status);

            if ($due === true) {
                $qb
                    ->andWhere('q.sendAt IS NULL OR q.sendAt <= :now')
                    ->setParameter('now', $now);
            } elseif ($due === false) {
                $qb
                    ->andWhere('q.sendAt IS NOT NULL AND q.sendAt > :now')
                    ->setParameter('now', $now);
            }

            if ($retryEligible === true) {
                $qb->andWhere('q.retryCount < :maxRetries')
                    ->setParameter('maxRetries', 3);
            } elseif ($retryEligible === false) {
                $qb->andWhere('q.retryCount >= :maxRetries')
                    ->setParameter('maxRetries', 3);
            }

            return (int) $qb->getQuery()->getSingleScalarResult();
        };

        $nextScheduledAt = $this->createQueryBuilder('q')
            ->select('MIN(q.sendAt)')
            ->andWhere('q.status IN (:statuses)')
            ->andWhere('q.sendAt IS NOT NULL AND q.sendAt > :now')
            ->setParameter('statuses', [SmsQueue::STATUS_PENDING, SmsQueue::STATUS_FAILED])
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'pendingDue' => $countStatus(SmsQueue::STATUS_PENDING, true),
            'pendingScheduled' => $countStatus(SmsQueue::STATUS_PENDING, false),
            'failedDue' => $countStatus(SmsQueue::STATUS_FAILED, true, true),
            'failedScheduled' => $countStatus(SmsQueue::STATUS_FAILED, false, true),
            'failedExhausted' => $countStatus(SmsQueue::STATUS_FAILED, null, false),
            'sending' => $countStatus(SmsQueue::STATUS_SENDING),
            'sent' => $countStatus(SmsQueue::STATUS_SENT),
            'cancelled' => $countStatus(SmsQueue::STATUS_CANCELLED),
            'nextScheduledAt' => $nextScheduledAt instanceof DateTimeImmutable ? $nextScheduledAt : (is_string($nextScheduledAt) && $nextScheduledAt !== '' ? new DateTimeImmutable($nextScheduledAt) : null),
        ];
    }
}