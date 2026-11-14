<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine;

use App\Patient\Domain\Entity\Allergy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AllergyRepository extends ServiceEntityRepository implements \App\Patient\Domain\Repository\AllergyRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Allergy::class);
    }
}