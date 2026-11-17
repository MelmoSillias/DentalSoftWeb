<?php

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository implements \App\Inventory\Domain\Repository\StockRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    public function findHistory(\DateTimeInterface $start, \DateTimeInterface $end, ?int $consumableId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->where('s.datePrise BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.datePrise', 'DESC');

        if ($consumableId) {
            $queryBuilder
                ->andWhere('s.consommable = :consumableId')
                ->setParameter('consumableId', $consumableId);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findBetweenDates(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.datePrise BETWEEN :start AND :end')
            ->setParameter('start', $start->format('Y-m-d 00:00:00'))
            ->setParameter('end', $end->format('Y-m-d 23:59:59'))
            ->orderBy('v.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}