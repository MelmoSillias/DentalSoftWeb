<?php

namespace App\ClinicalRecord\Repository;

use App\ClinicalRecord\Entity\DocumentMedical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentMedicalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentMedical::class);
    }
}
