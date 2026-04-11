<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserDeviceAccessLog;
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
    public function findLatestByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.device', 'd')->addSelect('d')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
