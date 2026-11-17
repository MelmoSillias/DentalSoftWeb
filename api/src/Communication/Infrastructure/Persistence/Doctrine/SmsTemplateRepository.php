<?php

namespace App\Communication\Infrastructure\Persistence\Doctrine;

use App\Communication\Domain\Entity\SmsTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SmsTemplate>
 */
class SmsTemplateRepository extends ServiceEntityRepository implements \App\Communication\Domain\Repository\SmsTemplateRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmsTemplate::class);
    }
}