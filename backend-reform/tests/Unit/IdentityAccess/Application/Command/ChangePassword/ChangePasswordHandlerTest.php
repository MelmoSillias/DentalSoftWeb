<?php

namespace App\Tests\Unit\IdentityAccess\Application\Command\ChangePassword;

use App\IdentityAccess\Application\Command\ChangePassword\ChangePasswordCommand;
use App\IdentityAccess\Application\Command\ChangePassword\ChangePasswordHandler;
use App\IdentityAccess\Application\Port\AuthWritePort;
use PHPUnit\Framework\TestCase;

final class ChangePasswordHandlerTest extends TestCase
{
    public function testDelegatesToAuthWritePort(): void
    {
        $data = [
            'oldPassword' => 'old-secret',
            'newPassword' => 'new-secret',
        ];
        $expected = ['status' => 'ok'];

        $port = $this->createMock(AuthWritePort::class);
        $port->expects(self::once())
            ->method('changePassword')
            ->with($data)
            ->willReturn($expected);

        $handler = new ChangePasswordHandler($port);
        $result = $handler(new ChangePasswordCommand($data));

        self::assertSame($expected, $result);
    }
}
