<?php

namespace App\CareDelivery\Infrastructure\Persistence\Doctrine;

use App\CareDelivery\Domain\Entity\ActeMedical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActeMedicalRepository extends ServiceEntityRepository implements \App\CareDelivery\Domain\Repository\ActeMedicalRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActeMedical::class);
    }
}
