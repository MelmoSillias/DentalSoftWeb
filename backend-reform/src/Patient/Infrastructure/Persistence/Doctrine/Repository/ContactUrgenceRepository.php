<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine\Repository;

use App\Patient\Infrastructure\Persistence\Doctrine\Entity\ContactUrgence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContactUrgenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactUrgence::class);
    }
}