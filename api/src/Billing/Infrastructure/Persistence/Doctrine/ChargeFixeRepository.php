<?php

namespace App\Billing\Infrastructure\Persistence\Doctrine;

use App\Billing\Domain\Entity\ChargeFixe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChargeFixe>
 */
class ChargeFixeRepository extends ServiceEntityRepository implements \App\Billing\Domain\Repository\ChargeFixeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChargeFixe::class);
    }
}