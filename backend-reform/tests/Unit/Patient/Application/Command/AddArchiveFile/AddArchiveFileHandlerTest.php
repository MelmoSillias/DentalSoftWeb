<?php

namespace App\Tests\Unit\Patient\Application\Command\AddArchiveFile;

use App\Patient\Application\Command\AddArchiveFile\AddArchiveFileCommand;
use App\Patient\Application\Command\AddArchiveFile\AddArchiveFileHandler;
use App\Patient\Application\Port\PatientFileStoragePort;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AddArchiveFileHandlerTest extends TestCase
{
    public function testDelegatesToFileStoragePort(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $expected = [
            'success' => true,
            'file' => ['nom' => 'scan', 'url' => '/uploads/patients/1/archive/x.pdf'],
        ];

        $port = $this->createMock(PatientFileStoragePort::class);
        $port->expects(self::once())
            ->method('addArchiveFile')
            ->with(1, 'scan', $file, '/public/uploads')
            ->willReturn($expected);

        $handler = new AddArchiveFileHandler($port);
        $result = $handler(new AddArchiveFileCommand(1, 'scan', $file, '/public/uploads'));

        self::assertSame($expected, $result);
    }
}
