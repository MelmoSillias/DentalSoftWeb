<?php

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\UserDeviceAccessLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDeviceAccessLog>
 */
class UserDeviceAccessLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDeviceAccessLog::class);
    }

    /** @return UserDeviceAccessLog[] */
    public function findLatest(int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.device', 'd')->addSelect('d')
            ->leftJoin('l.user', 'u')->addSelect('u')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
