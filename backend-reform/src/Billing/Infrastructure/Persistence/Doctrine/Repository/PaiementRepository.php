<?php

namespace App\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Paiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }
}
