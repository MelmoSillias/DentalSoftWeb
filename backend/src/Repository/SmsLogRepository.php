<?php

namespace App\Repository;

use App\Entity\SmsLog;
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

    /**
     * @return list<SmsLog>
     */
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

    /**
     * @return array<string, int>
     */
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

    /**
     * @return array<string, int>
     */
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
}
