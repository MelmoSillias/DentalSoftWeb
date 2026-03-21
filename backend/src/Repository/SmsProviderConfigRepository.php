<?php

namespace App\Repository;

use App\Entity\SmsProviderConfig;
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
        $config = $this->findOneBy(['provider' => 'orange']);
        if ($config instanceof SmsProviderConfig) {
            return $config;
        }

        $config = new SmsProviderConfig();
        $config->setProvider('orange');

        $em = $this->getEntityManager();
        $em->persist($config);
        $em->flush();

        return $config;
    }
}
