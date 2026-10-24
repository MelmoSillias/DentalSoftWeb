<?php

namespace App\Communication\Repository;

use App\Communication\Entity\WebPushSubscription;
use App\IdentityAccess\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebPushSubscription>
 */
class WebPushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebPushSubscription::class);
    }

    public function findOneByEndpoint(string $endpoint): ?WebPushSubscription
    {
        return $this->findOneBy(['endpointHash' => hash('sha256', $endpoint)]);
    }

    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<WebPushSubscription>
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
