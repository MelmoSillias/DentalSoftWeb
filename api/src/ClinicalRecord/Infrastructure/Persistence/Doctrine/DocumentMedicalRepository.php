<?php

namespace App\ClinicalRecord\Infrastructure\Persistence\Doctrine;

use App\ClinicalRecord\Entity\DocumentMedical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentMedicalRepository extends ServiceEntityRepository implements \App\ClinicalRecord\Domain\Repository\DocumentMedicalRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentMedical::class);
    }
}
