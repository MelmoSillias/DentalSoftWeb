<?php

namespace App\Settings\Domain\Repository;

use App\Settings\Domain\Model\AppSetting;
use App\Settings\Domain\ValueObject\AppSettingId;

interface AppSettingRepository
{
    public function save(AppSetting $setting): void;

    public function findById(AppSettingId $id): ?AppSetting;

    public function findByKey(string $keyName): ?AppSetting;
}
