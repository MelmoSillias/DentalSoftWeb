<?php

namespace App\Billing\Application\Port;

use DateTimeInterface;

interface BillingReadPort
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getTransactions(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getTransactionsByDateRange(DateTimeInterface $start, DateTimeInterface $end): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listModes(): array;

    /**
     * @return array{all: list<array<string, mixed>>}
     */
    public function listAllFactures(DateTimeInterface $start, DateTimeInterface $end): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listFacturesClassiques(DateTimeInterface $start, DateTimeInterface $end): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listFacturesImpayees(?DateTimeInterface $start, ?DateTimeInterface $end): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listPaiementsFactures(DateTimeInterface $start, DateTimeInterface $end): array;

    /**
     * @return array<string, mixed>|null
     */
    public function previewDevis(int $id): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function previewFacture(int $id): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function previewFactureDetail(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listFacturesAssurance(
        ?DateTimeInterface $start = null,
        ?DateTimeInterface $end = null,
        ?string $status = null,
        ?string $patientQuery = null,
        ?string $assuranceCode = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getClaimDetail(int $factureId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function mapFactureAssurancePrint(int $id): ?array;
}
