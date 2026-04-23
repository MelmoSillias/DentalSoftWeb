<?php

namespace App\Inventory\Repository;

use App\Inventory\Entity\Consommable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consommable>
 */
class ConsommableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consommable::class);
    }

    public function search(string $term): array
    {
        $qb = $this->createQueryBuilder('c');
        if ($term) {
            $qb->andWhere('LOWER(c.nom) LIKE :term')
                ->setParameter('term', '%' . strtolower($term) . '%');
        }
        return $qb->orderBy('c.nom', 'ASC')->getQuery()->getResult();
    }
}