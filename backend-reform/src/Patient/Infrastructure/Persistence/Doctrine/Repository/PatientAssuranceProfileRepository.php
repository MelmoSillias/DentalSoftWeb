<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine\Repository;

use App\Patient\Infrastructure\Persistence\Doctrine\Entity\PatientAssuranceProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PatientAssuranceProfile>
 */
class PatientAssuranceProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientAssuranceProfile::class);
    }
}
