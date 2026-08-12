<?php

namespace App\Tests\Unit\Communication\Domain\Model;

use App\Communication\Domain\Exception\CommunicationDomainException;
use App\Communication\Domain\Model\SmsQueueItem;
use App\Communication\Domain\ValueObject\SmsQueueId;
use PHPUnit\Framework\TestCase;

final class SmsQueueItemTest extends TestCase
{
    public function testMarkCancelledFromPending(): void
    {
        $item = SmsQueueItem::reconstitute(SmsQueueId::fromInt(1), '+221770000000', 'Hello', 'pending');

        $item->markCancelled();

        self::assertSame('cancelled', $item->getStatus());
    }

    public function testMarkCancelledRejectedWhenAlreadyCancelled(): void
    {
        $item = SmsQueueItem::reconstitute(SmsQueueId::fromInt(2), '+221770000000', 'Hello', 'cancelled');

        $this->expectException(CommunicationDomainException::class);
        $this->expectExceptionMessage('Sms queue item is already cancelled.');

        $item->markCancelled();
    }

    public function testMarkCancelledRejectedWhenSent(): void
    {
        $item = SmsQueueItem::reconstitute(SmsQueueId::fromInt(3), '+221770000000', 'Hello', 'sent');

        $this->expectException(CommunicationDomainException::class);
        $this->expectExceptionMessage('Cannot cancel a sent SMS.');

        $item->markCancelled();
    }
}
