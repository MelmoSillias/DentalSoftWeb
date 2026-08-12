<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine\Repository;

use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Allergy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AllergyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Allergy::class);
    }
}