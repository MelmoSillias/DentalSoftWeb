<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine;

use App\Patient\Domain\Entity\PatientAssuranceProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PatientAssuranceProfile>
 */
class PatientAssuranceProfileRepository extends ServiceEntityRepository implements \App\Patient\Domain\Repository\PatientAssuranceProfileRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientAssuranceProfile::class);
    }
}
