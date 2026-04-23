<?php

namespace App\ClinicalRecord\Repository;

use App\ClinicalRecord\Entity\FicheMedicale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FicheMedicaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheMedicale::class);
    }
}
