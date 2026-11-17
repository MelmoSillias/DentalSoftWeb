<?php

namespace App\Billing\Infrastructure\Persistence\Doctrine;

use App\Billing\Domain\Entity\FactureCabinet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FactureCabinet>
 */
class FactureCabinetRepository extends ServiceEntityRepository implements \App\Billing\Domain\Repository\FactureCabinetRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactureCabinet::class);
    }
}
