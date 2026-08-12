<?php

namespace App\Shared\Domain\Event;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredOn(): DateTimeImmutable;

    public function eventName(): string;
}
