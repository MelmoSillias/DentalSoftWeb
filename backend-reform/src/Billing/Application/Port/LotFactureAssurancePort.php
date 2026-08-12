<?php

namespace App\Billing\Application\Port;

use DateTimeInterface;

interface LotFactureAssurancePort
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getDashboard(): array;

    /**
     * @return array<string, mixed>
     */
    public function listLots(string $assuranceCode, ?string $statut = null): array;

    /**
     * @return array<string, mixed>
     */
    public function getLot(int $lotId): array;

    /**
     * @return array<string, mixed>
     */
    public function openLot(
        string $assuranceCode,
        ?string $description = null,
        ?DateTimeInterface $dateDebut = null,
        ?DateTimeInterface $dateFin = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function updateLot(
        int $lotId,
        ?string $description = null,
        ?DateTimeInterface $dateDebut = null,
        ?DateTimeInterface $dateFin = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function sendLot(int $lotId): array;

    /**
     * @return array<string, mixed>
     */
    public function reopenLot(int $lotId): array;

    /**
     * @return array<string, mixed>
     */
    public function confirmLot(int $lotId): array;

    /**
     * @return array<string, mixed>
     */
    public function unconfirmLot(int $lotId): array;

    /**
     * @return array<string, mixed>
     */
    public function refundLot(
        int $lotId,
        int $modeId,
        ?float $amount = null,
        ?DateTimeInterface $date = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelRefund(int $lotId, int $transactionId, ?string $comment = null): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelLotRecovery(int $lotId, ?string $comment = null): array;

    /**
     * @return array<string, mixed>
     */
    public function addClaimToLot(int $lotId, int $factureId): array;

    /**
     * @return array<string, mixed>
     */
    public function removeClaimFromLot(int $lotId, int $factureId): array;

    /**
     * @return array<string, mixed>
     */
    public function moveClaimToLot(int $factureId, int $targetLotId): array;
}
