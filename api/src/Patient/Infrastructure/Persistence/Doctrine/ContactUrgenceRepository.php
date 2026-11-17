<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine;

use App\Patient\Domain\Entity\ContactUrgence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContactUrgenceRepository extends ServiceEntityRepository implements \App\Patient\Domain\Repository\ContactUrgenceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactUrgence::class);
    }
}