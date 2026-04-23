<?php

namespace App\Communication\Repository;

use App\Communication\Entity\Notification;
use App\IdentityAccess\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findLatestForUser(User $user, int $limit = 20): array
    {
        return $this->createBaseQuery($user)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByFilter(User $user, string $filter = 'all', int $limit = 50): array
    {
        $qb = $this->createBaseQuery($user);

        if ($filter === 'read') {
            $qb->andWhere('n.etatVu = :read')->setParameter('read', 'vu');
        } elseif ($filter === 'unread') {
            $qb->andWhere('n.etatVu = :unread')->setParameter('unread', 'non_vu');
        }

        return $qb->setMaxResults($limit)->getQuery()->getResult();
    }

    public function findNewerThan(User $user, int $lastId): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.id > :lastId')
            ->setParameter('user', $user)
            ->setParameter('lastId', $lastId)
            ->orderBy('n.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.etatVu = :unread')
            ->setParameter('user', $user)
            ->setParameter('unread', 'non_vu')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUnreadWithLink(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.etatVu = :unread')
            ->andWhere('n.link IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('unread', 'non_vu')
            ->orderBy('n.dateEnvoi', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function purgeOlderThan(\DateTimeInterface $threshold): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.dateEnvoi < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    private function createBaseQuery(User $user)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.dateEnvoi', 'DESC')
            ->addOrderBy('n.id', 'DESC');
    }
}