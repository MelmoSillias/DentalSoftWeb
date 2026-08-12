<?php

namespace App\Communication\Domain\Exception;

use RuntimeException;

final class SmsQueueNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Sms queue item #%d not found.', $id));
    }
}
