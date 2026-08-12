<?php

namespace App\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Billing\Infrastructure\Persistence\Doctrine\Entity\ContenuDevis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContenuDevisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContenuDevis::class);
    }
}