<?php

namespace App\Scheduling\Domain\Model;

use App\Scheduling\Domain\Exception\SchedulingDomainException;
use App\Scheduling\Domain\ValueObject\RdvId;

/**
 * Appointment aggregate for strangler cutover.
 *
 * Legacy status codes (RdvService stats / handleAction):
 *  0  = pending
 *  1  = validated
 * -1  = reported (postponed)
 * -2  = cancelled
 */
final class Rdv
{
    public const STATUS_PENDING = 0;
    public const STATUS_VALIDATED = 1;
    public const STATUS_REPORTED = -1;
    public const STATUS_CANCELLED = -2;

    private function __construct(
        private ?RdvId $id,
        private int $status,
        private ?int $patientId,
        private ?int $medecinId,
    ) {
    }

    public static function reconstitute(RdvId $id, int $status, ?int $patientId, ?int $medecinId): self
    {
        return new self($id, $status, $patientId, $medecinId);
    }

    public function validate(): void
    {
        $this->assertCanLeaveCurrentStatus('validate');
        if ($this->status !== self::STATUS_PENDING) {
            throw new SchedulingDomainException('Only a pending RDV can be validated.');
        }
        $this->status = self::STATUS_VALIDATED;
    }

    public function cancel(): void
    {
        $this->assertCanLeaveCurrentStatus('cancel');
        if (!in_array($this->status, [self::STATUS_PENDING, self::STATUS_VALIDATED], true)) {
            throw new SchedulingDomainException('Only a pending or validated RDV can be cancelled.');
        }
        $this->status = self::STATUS_CANCELLED;
    }

    public function report(): void
    {
        $this->assertCanLeaveCurrentStatus('report');
        if ($this->status !== self::STATUS_PENDING) {
            throw new SchedulingDomainException('Only a pending RDV can be reported.');
        }
        $this->status = self::STATUS_REPORTED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isReported(): bool
    {
        return $this->status === self::STATUS_REPORTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getId(): ?RdvId
    {
        return $this->id;
    }

    public function requireId(): RdvId
    {
        if ($this->id === null) {
            throw new SchedulingDomainException('Rdv id is not assigned.');
        }

        return $this->id;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getPatientId(): ?int
    {
        return $this->patientId;
    }

    public function getMedecinId(): ?int
    {
        return $this->medecinId;
    }

    private function assertCanLeaveCurrentStatus(string $action): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            throw new SchedulingDomainException(sprintf('Cannot %s a cancelled RDV.', $action));
        }
        if ($this->status === self::STATUS_REPORTED) {
            throw new SchedulingDomainException(sprintf('Cannot %s a reported RDV.', $action));
        }
    }
}
