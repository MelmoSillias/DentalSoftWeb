<?php

namespace App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Entity\DocumentMedical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentMedicalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentMedical::class);
    }
}
