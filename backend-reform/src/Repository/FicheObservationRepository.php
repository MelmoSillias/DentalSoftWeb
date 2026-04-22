<?php

namespace App\Repository;

use App\Entity\Devis;
use App\Entity\FicheObservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FicheObservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheObservation::class);
    }

    // Ajoutez vos requêtes personnalisées ici
    // src/Repository/FicheRepository.php
   

}
