<?php

namespace App\Tests\Unit\Communication\Application\Command\QueueManualSms;

use App\Communication\Application\Command\QueueManualSms\QueueManualSmsCommand;
use App\Communication\Application\Command\QueueManualSms\QueueManualSmsHandler;
use App\Communication\Application\Port\SmsWritePort;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class QueueManualSmsHandlerTest extends TestCase
{
    public function testDelegatesToSmsWritePort(): void
    {
        $sendAt = new DateTimeImmutable('2026-08-10 09:00:00');
        $expected = ['success' => true, 'queueId' => 42];

        $writePort = $this->createMock(SmsWritePort::class);
        $writePort->expects(self::once())
            ->method('queueManual')
            ->with('770000000', 'Bonjour', 7, 'manual', 'manual', $sendAt, ['recurrence' => 'none'])
            ->willReturn($expected);

        $handler = new QueueManualSmsHandler($writePort);
        $result = $handler(new QueueManualSmsCommand(
            '770000000',
            'Bonjour',
            7,
            'manual',
            'manual',
            $sendAt,
            ['recurrence' => 'none'],
        ));

        self::assertSame($expected, $result);
    }
}
