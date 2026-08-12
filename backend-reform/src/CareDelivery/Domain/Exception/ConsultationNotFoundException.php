<?php

namespace App\CareDelivery\Domain\Exception;

use RuntimeException;

final class ConsultationNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Consultation #%d not found.', $id));
    }
}
