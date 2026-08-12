<?php

namespace App\Tests\Unit\Settings\Application\Command\UpdateGeneralSettings;

use App\Settings\Application\Command\UpdateGeneralSettings\UpdateGeneralSettingsCommand;
use App\Settings\Application\Command\UpdateGeneralSettings\UpdateGeneralSettingsHandler;
use App\Settings\Application\Port\SettingsWritePort;
use PHPUnit\Framework\TestCase;

final class UpdateGeneralSettingsHandlerTest extends TestCase
{
    public function testDelegatesToSettingsWritePort(): void
    {
        $payload = ['cabinetName' => 'ORODENT'];
        $expected = ['success' => true, 'settings' => $payload];

        $writePort = $this->createMock(SettingsWritePort::class);
        $writePort->expects(self::once())
            ->method('saveGeneralSettings')
            ->with($payload)
            ->willReturn($expected);

        $handler = new UpdateGeneralSettingsHandler($writePort);
        $result = $handler(new UpdateGeneralSettingsCommand($payload));

        self::assertSame($expected, $result);
    }
}
