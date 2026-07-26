<?php

namespace App\Communication\Repository;

use App\Communication\Entity\SmsProviderConfig;
use App\Communication\Service\SmsClientResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SmsProviderConfig>
 */
class SmsProviderConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmsProviderConfig::class);
    }

    public function getMainConfig(): SmsProviderConfig
    {
        $config = $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($config instanceof SmsProviderConfig) {
            return $config;
        }

        $config = new SmsProviderConfig();
        $config->setProvider(SmsClientResolver::PROVIDER_ORANGE);

        $em = $this->getEntityManager();
        $em->persist($config);
        $em->flush();

        return $config;
    }
}
