<?php

namespace App\Repository;

use App\Entity\Consommable;
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

    //    /**
    //     * @return Consommable[] Returns an array of Consommable objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Consommable
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
