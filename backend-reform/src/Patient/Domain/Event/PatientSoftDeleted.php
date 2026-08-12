<?php

namespace App\Patient\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final class PatientSoftDeleted extends AbstractDomainEvent
{
    public function __construct(private readonly int $patientId)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'patient.soft_deleted';
    }

    public function patientId(): int
    {
        return $this->patientId;
    }
}
