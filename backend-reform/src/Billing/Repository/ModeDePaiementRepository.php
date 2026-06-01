<?php

namespace App\Billing\Repository;

use App\Billing\Entity\ModeDePaiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModeDePaiement>
 *
 * @method ModeDePaiement|null find($id, $lockMode = null, $lockVersion = null)
 * @method ModeDePaiement|null findOneBy(array $criteria, array $orderBy = null)
 * @method ModeDePaiement[]    findAll()
 * @method ModeDePaiement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ModeDePaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModeDePaiement::class);
    }

    public function findActifs(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.actif = :val')
            ->setParameter('val', true)
            ->orderBy('m.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.type = :type')
            ->setParameter('type', $type)
            ->andWhere('m.actif = true')
            ->orderBy('m.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findClassics(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}