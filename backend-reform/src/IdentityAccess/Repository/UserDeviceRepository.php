<?php

namespace App\IdentityAccess\Repository;

use App\IdentityAccess\Entity\UserDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDevice>
 */
class UserDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDevice::class);
    }

    public function findOneByIdentifier(string $identifier): ?UserDevice
    {
        return $this->findOneBy(['deviceIdentifier' => $identifier]);
    }

    /** @return UserDevice[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countApproved(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', UserDevice::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array{approved:int,pending:int,rejected:int,total:int} */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.status AS status')
            ->addSelect('COUNT(d.id) AS total')
            ->groupBy('d.status')
            ->getQuery()
            ->getArrayResult();

        $stats = ['approved' => 0, 'pending' => 0, 'rejected' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['total'] ?? 0);
            if (array_key_exists($status, $stats)) {
                $stats[$status] = $count;
            }
            $stats['total'] += $count;
        }

        return $stats;
    }
}
