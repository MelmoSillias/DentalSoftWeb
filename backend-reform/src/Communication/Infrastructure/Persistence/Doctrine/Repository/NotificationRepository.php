<?php

namespace App\Communication\Infrastructure\Persistence\Doctrine\Repository;

use App\Communication\Infrastructure\Persistence\Doctrine\Entity\Notification;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
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

    public function markUnreadMatchingPathAsRead(User $user, string $path, int $limit = 50): int
    {
        $rows = $this->createQueryBuilder('n')
            ->select('n.id, n.link')
            ->andWhere('n.user = :user')
            ->andWhere('n.etatVu = :unread')
            ->andWhere('n.link IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('unread', 'non_vu')
            ->orderBy('n.dateEnvoi', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $ids = [];
        foreach ($rows as $row) {
            $link = isset($row['link']) ? (string) $row['link'] : '';
            if ($link === '') {
                continue;
            }

            $linkPath = parse_url($link, PHP_URL_PATH);
            if (!is_string($linkPath) || $linkPath === '') {
                $linkPath = $link;
            }

            if ($linkPath !== '' && str_starts_with($path, $linkPath)) {
                $ids[] = (int) $row['id'];
            }
        }

        if ($ids === []) {
            return 0;
        }

        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.etatVu', ':read')
            ->andWhere('n.id IN (:ids)')
            ->andWhere('n.etatVu = :unread')
            ->setParameter('read', 'vu')
            ->setParameter('unread', 'non_vu')
            ->setParameter('ids', array_values(array_unique($ids)))
            ->getQuery()
            ->execute();
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