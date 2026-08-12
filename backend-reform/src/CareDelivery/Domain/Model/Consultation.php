<?php

namespace App\CareDelivery\Domain\Model;

use App\CareDelivery\Domain\Exception\CareDeliveryDomainException;
use App\CareDelivery\Domain\ValueObject\ConsultationId;

/**
 * Consultation aggregate.
 * Legacy statut: 0 = open/active, 1 = closed. Cancellation is a domain flag (delete path).
 */
final class Consultation
{
    private const STATUS_OPEN = 0;
    private const STATUS_CLOSED = 1;

    /** @var list<ActeMedical> */
    private array $actes;

    /**
     * @param list<ActeMedical> $actes
     */
    private function __construct(
        private ?ConsultationId $id,
        private int $patientId,
        private int $status,
        private ?int $medecinId = null,
        private ?int $ficheId = null,
        array $actes = [],
        private bool $cancelled = false,
    ) {
        if ($this->patientId <= 0) {
            throw new CareDeliveryDomainException('Consultation requires a valid patientId.');
        }

        if (!in_array($this->status, [self::STATUS_OPEN, self::STATUS_CLOSED], true)) {
            throw new CareDeliveryDomainException('Consultation status is invalid.');
        }

        if ($this->medecinId !== null && $this->medecinId <= 0) {
            throw new CareDeliveryDomainException('Consultation medecinId is invalid.');
        }

        if ($this->ficheId !== null && $this->ficheId <= 0) {
            throw new CareDeliveryDomainException('Consultation ficheId is invalid.');
        }

        $this->actes = array_values($actes);
    }

    public static function create(int $patientId, ?int $medecinId = null): self
    {
        return new self(null, $patientId, self::STATUS_OPEN, $medecinId);
    }

    /**
     * @param list<ActeMedical> $actes
     */
    public static function reconstitute(
        ConsultationId $id,
        int $patientId,
        int $status,
        ?int $medecinId = null,
        ?int $ficheId = null,
        array $actes = [],
        bool $cancelled = false,
    ): self {
        return new self($id, $patientId, $status, $medecinId, $ficheId, $actes, $cancelled);
    }

    public function assertOpen(): void
    {
        $this->assertNotCancelled();

        if (!$this->isOpen()) {
            throw new CareDeliveryDomainException('Consultation is not open.');
        }
    }

    public function requireMedecinForSave(): void
    {
        if ($this->medecinId === null) {
            throw new CareDeliveryDomainException('Consultation requires a medecin before save.');
        }
    }

    public function requireMedecinForClose(): void
    {
        if ($this->medecinId === null) {
            throw new CareDeliveryDomainException('Consultation requires a medecin before close.');
        }
    }

    public function assignMedecinIfUnassigned(int $medecinId): void
    {
        if ($medecinId <= 0) {
            throw new CareDeliveryDomainException('Medecin id is invalid.');
        }

        if ($this->medecinId === null) {
            $this->medecinId = $medecinId;

            return;
        }

        if ($this->medecinId !== $medecinId) {
            throw new CareDeliveryDomainException('Consultation is already assigned to a different medecin.');
        }
    }

    /**
     * @param list<ActeMedical> $actes
     */
    public function replaceActes(array $actes): void
    {
        $this->assertOpen();
        $this->actes = array_values($actes);
    }

    public function close(): void
    {
        $this->assertNotCancelled();

        if ($this->isClosed()) {
            throw new CareDeliveryDomainException('Consultation is already closed.');
        }

        $this->status = self::STATUS_CLOSED;
    }

    public function reopen(): void
    {
        $this->assertNotCancelled();

        if (!$this->isClosed()) {
            throw new CareDeliveryDomainException('Consultation is not closed.');
        }

        $this->status = self::STATUS_OPEN;
    }

    /**
     * Domain cancellation of an open consultation (maps to legacy delete / "cancelled" event).
     * There is no dedicated statut value in legacy storage.
     */
    public function cancel(): void
    {
        if ($this->cancelled) {
            throw new CareDeliveryDomainException('Consultation is already cancelled.');
        }

        if ($this->isClosed()) {
            throw new CareDeliveryDomainException('Closed consultation cannot be cancelled.');
        }

        $this->cancelled = true;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && !$this->cancelled;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function getId(): ?ConsultationId
    {
        return $this->id;
    }

    public function requireId(): ConsultationId
    {
        if ($this->id === null) {
            throw new CareDeliveryDomainException('Consultation id is not assigned.');
        }

        return $this->id;
    }

    public function getPatientId(): int
    {
        return $this->patientId;
    }

    public function getMedecinId(): ?int
    {
        return $this->medecinId;
    }

    public function getFicheId(): ?int
    {
        return $this->ficheId;
    }

    /**
     * @return list<ActeMedical>
     */
    public function getActes(): array
    {
        return $this->actes;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    private function assertNotCancelled(): void
    {
        if ($this->cancelled) {
            throw new CareDeliveryDomainException('Consultation is cancelled.');
        }
    }
}
