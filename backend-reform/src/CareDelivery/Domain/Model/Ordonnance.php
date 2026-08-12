<?php

namespace App\CareDelivery\Domain\Model;

use App\CareDelivery\Domain\Exception\CareDeliveryDomainException;
use App\CareDelivery\Domain\ValueObject\OrdonnanceId;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Ordonnance aggregate: at least one line with non-empty designation.
 */
final class Ordonnance
{
    /** @var list<OrdonnanceLigne> */
    private array $lignes;

    /**
     * @param list<OrdonnanceLigne> $lignes
     */
    private function __construct(
        private ?OrdonnanceId $id,
        private int $consultationId,
        private DateTimeInterface $date,
        array $lignes,
        private ?string $medecinNom = null,
        private ?string $note = null,
    ) {
        if ($this->consultationId <= 0) {
            throw new CareDeliveryDomainException('Ordonnance requires a valid consultationId.');
        }

        $this->assertHasLines($lignes);
        $this->lignes = array_values($lignes);
    }

    /**
     * @param list<OrdonnanceLigne> $lignes
     */
    public static function create(
        int $consultationId,
        array $lignes,
        ?DateTimeInterface $date = null,
        ?string $medecinNom = null,
        ?string $note = null,
    ): self {
        return new self(
            null,
            $consultationId,
            $date ?? new DateTimeImmutable(),
            $lignes,
            $medecinNom,
            $note,
        );
    }

    /**
     * @param list<OrdonnanceLigne> $lignes
     */
    public static function reconstitute(
        OrdonnanceId $id,
        int $consultationId,
        DateTimeInterface $date,
        array $lignes,
        ?string $medecinNom = null,
        ?string $note = null,
    ): self {
        return new self($id, $consultationId, $date, $lignes, $medecinNom, $note);
    }

    /**
     * @param list<OrdonnanceLigne> $lignes
     */
    public function replaceLines(array $lignes): void
    {
        $this->assertHasLines($lignes);
        $this->lignes = array_values($lignes);
    }

    public function getId(): ?OrdonnanceId
    {
        return $this->id;
    }

    public function requireId(): OrdonnanceId
    {
        if ($this->id === null) {
            throw new CareDeliveryDomainException('Ordonnance id is not assigned.');
        }

        return $this->id;
    }

    public function getConsultationId(): int
    {
        return $this->consultationId;
    }

    public function getDate(): DateTimeInterface
    {
        return $this->date;
    }

    public function getMedecinNom(): ?string
    {
        return $this->medecinNom;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * @return list<OrdonnanceLigne>
     */
    public function getLignes(): array
    {
        return $this->lignes;
    }

    /**
     * @param list<OrdonnanceLigne> $lignes
     */
    private function assertHasLines(array $lignes): void
    {
        if ($lignes === []) {
            throw new CareDeliveryDomainException('Ordonnance requires at least one line.');
        }
    }
}
