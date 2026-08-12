<?php

namespace App\Settings\Infrastructure\Persistence\Doctrine\Repository;

use App\Settings\Domain\Model\AppSetting;
use App\Settings\Domain\Repository\AppSettingRepository;
use App\Settings\Domain\ValueObject\AppSettingId;
use App\Settings\Infrastructure\Persistence\Doctrine\Entity\AppSetting as EntityAppSetting;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAppSettingRepository implements AppSettingRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(AppSetting $setting): void
    {
        $id = $setting->getId();
        if ($id === null) {
            throw new \RuntimeException('Creating settings via Domain repository is not supported in this strangler slice.');
        }

        $entity = $this->em->find(EntityAppSetting::class, $id->toInt());
        if (!$entity instanceof EntityAppSetting) {
            throw new \RuntimeException(sprintf('AppSetting entity #%d not found for save.', $id->toInt()));
        }

        $entity->setValue($setting->getValue());
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(AppSettingId $id): ?AppSetting
    {
        $entity = $this->em->find(EntityAppSetting::class, $id->toInt());
        if (!$entity instanceof EntityAppSetting) {
            return null;
        }

        return AppSetting::reconstitute(
            AppSettingId::fromInt((int) $entity->getId()),
            $entity->getKeyName(),
            $entity->getValue(),
        );
    }

    public function findByKey(string $keyName): ?AppSetting
    {
        $entity = $this->em->getRepository(EntityAppSetting::class)->findOneBy(['keyName' => $keyName]);
        if (!$entity instanceof EntityAppSetting) {
            return null;
        }

        return AppSetting::reconstitute(
            AppSettingId::fromInt((int) $entity->getId()),
            $entity->getKeyName(),
            $entity->getValue(),
        );
    }
}
