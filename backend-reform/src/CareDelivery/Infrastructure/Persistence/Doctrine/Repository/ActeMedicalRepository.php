<?php

namespace App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository;

use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\ActeMedical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActeMedicalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActeMedical::class);
    }
}
