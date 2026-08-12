<?php

namespace App\Tests\Unit\IdentityAccess\Application\Command\UpdateUserProfile;

use App\IdentityAccess\Application\Command\UpdateUserProfile\UpdateUserProfileCommand;
use App\IdentityAccess\Application\Command\UpdateUserProfile\UpdateUserProfileHandler;
use App\IdentityAccess\Application\Port\AuthWritePort;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class UpdateUserProfileHandlerTest extends TestCase
{
    public function testDelegatesToAuthWritePort(): void
    {
        $data = [
            'username' => 'alice',
            'notificationsEnabled' => true,
            'nom' => 'Doe',
        ];
        $photo = $this->createMock(UploadedFile::class);
        $uploadDir = '/tmp/uploads';
        $expected = ['status' => 'ok'];

        $port = $this->createMock(AuthWritePort::class);
        $port->expects(self::once())
            ->method('updateMe')
            ->with($data, $photo, $uploadDir)
            ->willReturn($expected);

        $handler = new UpdateUserProfileHandler($port);
        $result = $handler(new UpdateUserProfileCommand($data, $photo, $uploadDir));

        self::assertSame($expected, $result);
    }

    public function testRejectsInvalidEmailBeforePortCall(): void
    {
        $port = $this->createMock(AuthWritePort::class);
        $port->expects(self::never())->method('updateMe');

        $handler = new UpdateUserProfileHandler($port);

        $this->expectException(BadRequestHttpException::class);
        $handler(new UpdateUserProfileCommand([
            'username' => 'alice',
            'email' => 'not-an-email',
        ]));
    }
}
