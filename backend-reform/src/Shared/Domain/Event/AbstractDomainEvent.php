<?php

namespace App\Shared\Domain\Event;

use DateTimeImmutable;

abstract class AbstractDomainEvent implements DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(?DateTimeImmutable $occurredOn = null)
    {
        $this->occurredOn = $occurredOn ?? new DateTimeImmutable();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
