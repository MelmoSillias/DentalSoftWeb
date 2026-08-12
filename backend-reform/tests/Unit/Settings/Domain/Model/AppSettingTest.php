<?php

namespace App\Tests\Unit\Settings\Domain\Model;

use App\Settings\Domain\Model\AppSetting;
use App\Settings\Domain\ValueObject\AppSettingId;
use PHPUnit\Framework\TestCase;

final class AppSettingTest extends TestCase
{
    public function testReplaceValueUpdatesPayload(): void
    {
        $setting = AppSetting::reconstitute(
            AppSettingId::fromInt(1),
            'general',
            ['cabinetName' => 'Old'],
        );

        $setting->replaceValue(['cabinetName' => 'ORODENT', 'locale' => 'fr']);

        self::assertSame('general', $setting->getKeyName());
        self::assertSame(['cabinetName' => 'ORODENT', 'locale' => 'fr'], $setting->getValue());
    }
}
