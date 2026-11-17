<?php

namespace App\Scheduling\Infrastructure\Persistence\Doctrine;

use App\Scheduling\Domain\Entity\Conge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conge>
 */
class CongeRepository extends ServiceEntityRepository implements \App\Scheduling\Domain\Repository\CongeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conge::class);
    }

    public function save(Conge $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Conge $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}