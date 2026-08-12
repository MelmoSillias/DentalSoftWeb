<?php

namespace App\Billing\Application\Port;

use DateTimeInterface;

interface InsuredBillingPort
{
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
     * @return array<string, mixed>
     */
    public function payPatientShare(
        int $factureId,
        int $modeId,
        ?float $amount = null,
        ?DateTimeInterface $date = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function resetPayments(int $factureId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function mapFactureAssurancePrint(int $id): ?array;
}
