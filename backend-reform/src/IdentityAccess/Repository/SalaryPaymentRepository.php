<?php

namespace App\IdentityAccess\Repository;

use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\SalaryPayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalaryPayment>
 */
class SalaryPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalaryPayment::class);
    }

    public function findByFiltersPaginated(int $start, int $length, ?int $employeeId, ?int $month, ?int $year): array
    {
        $qb = $this->createQueryBuilder('sp')
            ->leftJoin('sp.employe', 'e')->addSelect('e')
            ->orderBy('sp.paidAt', 'DESC')
            ->addOrderBy('sp.id', 'DESC');

        $this->applyFilters($qb, $employeeId, $month, $year);

        return $qb
            ->setFirstResult($start)
            ->setMaxResults($length)
            ->getQuery()
            ->getResult();
    }

    public function countByFilters(?int $employeeId, ?int $month, ?int $year): int
    {
        $qb = $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id)');

        $this->applyFilters($qb, $employeeId, $month, $year);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findLastPaymentForEmployee(Employe $employee): ?SalaryPayment
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.employe = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('sp.paidAt', 'DESC')
            ->addOrderBy('sp.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function applyFilters($qb, ?int $employeeId, ?int $month, ?int $year): void
    {
        if ($employeeId) {
            $qb->andWhere('sp.employe = :employeeId')
                ->setParameter('employeeId', $employeeId);
        }

        if ($month) {
            $qb->andWhere('sp.month = :month')
                ->setParameter('month', $month);
        }

        if ($year) {
            $qb->andWhere('sp.year = :year')
                ->setParameter('year', $year);
        }
    }
}
