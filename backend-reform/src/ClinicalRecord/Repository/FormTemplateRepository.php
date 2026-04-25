<?php

namespace App\ClinicalRecord\Repository;

use App\ClinicalRecord\Entity\FormTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FormTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormTemplate::class);
    }

    public function findLatestByKey(string $key): ?FormTemplate
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.key = :key')
            ->setParameter('key', $key)
            ->orderBy('ft.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
