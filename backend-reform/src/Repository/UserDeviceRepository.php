<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserDevice;
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

    public function findOneByUserAndIdentifier(User $user, string $identifier): ?UserDevice
    {
        return $this->findOneBy([
            'user' => $user,
            'deviceIdentifier' => $identifier,
        ]);
    }

    /** @return UserDevice[] */
    public function findByUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countApprovedByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.user = :user')
            ->andWhere('d.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', UserDevice::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<int, array{userId:int,approvedCount:int,pendingCount:int,totalCount:int}> */
    public function countStatsGroupedByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->select('IDENTITY(d.user) AS userId')
            ->addSelect('SUM(CASE WHEN d.status = :approved THEN 1 ELSE 0 END) AS approvedCount')
            ->addSelect('SUM(CASE WHEN d.status = :pending THEN 1 ELSE 0 END) AS pendingCount')
            ->addSelect('COUNT(d.id) AS totalCount')
            ->andWhere('d.user IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->setParameter('approved', UserDevice::STATUS_APPROVED)
            ->setParameter('pending', UserDevice::STATUS_PENDING)
            ->groupBy('d.user')
            ->getQuery()
            ->getArrayResult();
    }
}
