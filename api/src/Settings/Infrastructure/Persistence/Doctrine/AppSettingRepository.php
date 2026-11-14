<?php

namespace App\Settings\Infrastructure\Persistence\Doctrine;

use App\Settings\Domain\Entity\AppSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppSetting>
 */
class AppSettingRepository extends ServiceEntityRepository implements \App\Settings\Domain\Repository\AppSettingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppSetting::class);
    }

    public function findOneByKey(string $key): ?AppSetting
    {
        return $this->findOneBy(['keyName' => $key]);
    }
}