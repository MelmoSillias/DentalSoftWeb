<?php

namespace App\Communication\Infrastructure\Persistence\Doctrine\Repository;

use App\Communication\Infrastructure\Persistence\Doctrine\Entity\SmsLog;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SmsLog>
 */
class SmsLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmsLog::class);
    }

    public function countSentBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.createdAt BETWEEN :start AND :end')
            ->andWhere('l.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'sent')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTotalSent(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.status = :status')
            ->setParameter('status', 'sent')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecent(int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.patient', 'p')
            ->addSelect('p')
            ->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, min($limit, 200)))
            ->getQuery()
            ->getResult();
    }

    public function dailySentSeries(int $days = 7): array
    {
        $days = max(1, min($days, 31));
        $start = (new DateTimeImmutable('today'))->modify(sprintf('-%d days', $days - 1));

        $rows = $this->createQueryBuilder('l')
            ->select('l.createdAt AS createdAt')
            ->andWhere('l.createdAt >= :start')
            ->andWhere('l.status = :status')
            ->setParameter('start', $start)
            ->setParameter('status', 'sent')
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $series = [];
        for ($i = 0; $i < $days; ++$i) {
            $day = $start->modify(sprintf('+%d days', $i))->format('Y-m-d');
            $series[$day] = 0;
        }

        foreach ($rows as $row) {
            $key = ($row['createdAt'] instanceof DateTimeInterface)
                ? $row['createdAt']->format('Y-m-d')
                : (new DateTimeImmutable((string) $row['createdAt']))->format('Y-m-d');

            if (isset($series[$key])) {
                ++$series[$key];
            }
        }

        return $series;
    }

    public function monthlySentSeries(int $months = 6): array
    {
        $months = max(1, min($months, 24));
        $firstMonth = (new DateTimeImmutable('first day of this month'))->modify(sprintf('-%d months', $months - 1));

        $rows = $this->createQueryBuilder('l')
            ->select('l.createdAt AS createdAt')
            ->andWhere('l.createdAt >= :start')
            ->andWhere('l.status = :status')
            ->setParameter('start', $firstMonth)
            ->setParameter('status', 'sent')
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $series = [];
        for ($i = 0; $i < $months; ++$i) {
            $month = $firstMonth->modify(sprintf('+%d months', $i))->format('Y-m');
            $series[$month] = 0;
        }

        foreach ($rows as $row) {
            $key = ($row['createdAt'] instanceof DateTimeInterface)
                ? $row['createdAt']->format('Y-m')
                : (new DateTimeImmutable((string) $row['createdAt']))->format('Y-m');

            if (isset($series[$key])) {
                ++$series[$key];
            }
        }

        return $series;
    }

    /**
     * @return SmsLog[]
     */
    public function findByPhoneSince(string $phone, \DateTimeImmutable $since, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.patient', 'p')
            ->addSelect('p')
            ->andWhere('l.phone = :phone')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('phone', $phone)
            ->setParameter('since', $since)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 200)))
            ->getQuery()
            ->getResult();
    }

    public function countByStatusBetween(DateTimeInterface $start, DateTimeInterface $end, string $status): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.createdAt BETWEEN :start AND :end')
            ->andWhere('l.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function dailySentSeriesBetween(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $startDay = DateTimeImmutable::createFromInterface($start)->setTime(0, 0, 0);
        $endDay = DateTimeImmutable::createFromInterface($end)->setTime(0, 0, 0);

        $rows = $this->createQueryBuilder('l')
            ->select('l.createdAt AS createdAt')
            ->andWhere('l.createdAt BETWEEN :start AND :end')
            ->andWhere('l.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'sent')
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $series = [];
        $cursor = $startDay;
        while ($cursor <= $endDay) {
            $series[$cursor->format('Y-m-d')] = 0;
            $cursor = $cursor->modify('+1 day');
        }

        foreach ($rows as $row) {
            $key = ($row['createdAt'] instanceof DateTimeInterface)
                ? $row['createdAt']->format('Y-m-d')
                : (new DateTimeImmutable((string) $row['createdAt']))->format('Y-m-d');

            if (isset($series[$key])) {
                ++$series[$key];
            }
        }

        return $series;
    }

    /**
     * Sent SMS counts grouped by type for the given period.
     *
     * @return array<string, int>
     */
    public function sentByTypeBetween(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.type AS type, COUNT(l.id) AS cnt')
            ->andWhere('l.createdAt BETWEEN :start AND :end')
            ->andWhere('l.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'sent')
            ->groupBy('l.type')
            ->orderBy('COUNT(l.id)', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $byType = [];
        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? 'unknown');
            $byType[$type] = (int) ($row['cnt'] ?? 0);
        }

        return $byType;
    }

    public function findLatestByProviderMessageId(string $providerMessageId, string $provider): ?SmsLog
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.providerMessageId = :providerMessageId')
            ->andWhere('l.provider = :provider')
            ->setParameter('providerMessageId', $providerMessageId)
            ->setParameter('provider', $provider)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}