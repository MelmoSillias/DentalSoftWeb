<?php

namespace App\Repository;

use App\Entity\PaiementDevis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaiementDevisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaiementDevis::class);
    }

    // Ajoutez vos requêtes personnalisées ici
}
