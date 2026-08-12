<?php

namespace App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Entity\ExamenDentaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ExamenDentaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamenDentaire::class);
    }

    // Ajoutez vos requêtes personnalisées ici
}
