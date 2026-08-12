<?php

namespace App\Billing\Application\Port;

use DateTimeImmutable;
use DateTimeInterface;

interface BillingWritePort
{
    /**
     * @return array<string, mixed>
     */
    public function createTransaction(
        string $type,
        float $montant,
        ?string $description,
        DateTimeInterface $date,
        int $modeId,
        ?string $motif = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function updateTransactionValidationStatus(
        int $id,
        string $status,
        ?string $comment = null,
        ?DateTimeImmutable $validatedAt = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function deleteTransaction(int $id): array;

    /**
     * @return array<string, mixed>
     */
    public function transferInterCompte(
        int $fromId,
        int $toId,
        float $montant,
        string $motif,
        DateTimeInterface $date,
    ): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function payerFacture(int $id, array $payload = []): array;

    /**
     * @return array<string, mixed>
     */
    public function resetFacturePayments(int $id): array;

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
    public function resetFactureAssurancePayments(int $factureId): array;

    /**
     * Persist devis payload for a clinical fiche (legacy ClinicalRecord path).
     *
     * @param array<string, mixed> $data
     */
    public function updateDevis(int $ficheId, array $data): void;
}
