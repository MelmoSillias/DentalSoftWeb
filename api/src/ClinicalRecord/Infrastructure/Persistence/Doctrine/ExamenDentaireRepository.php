<?php

namespace App\ClinicalRecord\Infrastructure\Persistence\Doctrine;

use App\ClinicalRecord\Entity\ExamenDentaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ExamenDentaireRepository extends ServiceEntityRepository implements \App\ClinicalRecord\Domain\Repository\ExamenDentaireRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamenDentaire::class);
    }

    // Ajoutez vos requêtes personnalisées ici
}
