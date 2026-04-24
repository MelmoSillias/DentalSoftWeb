<?php

namespace App\Dto\Focus;

final class FocusReceptionConsultationDto
{
    public function __construct(private array $payload)
    {
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}