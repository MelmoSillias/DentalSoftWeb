<?php

namespace App\Billing\Infrastructure\Persistence\Doctrine;

use App\Billing\Domain\Entity\ServiceCabinet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceCabinet>
 */
class ServiceCabinetRepository extends ServiceEntityRepository implements \App\Billing\Domain\Repository\ServiceCabinetRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceCabinet::class);
    }
}
