<?php

namespace App\Repository;

use App\Entity\ContenuDevis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContenuDevisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContenuDevis::class);
    }

    // Ajoutez vos requêtes personnalisées ici
}
