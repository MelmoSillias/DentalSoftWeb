<?php

namespace App\Billing\Infrastructure\Adapter;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Billing\Service\LotFactureAssuranceService;
use DateTimeInterface;

final class LegacyLotFactureAssuranceAdapter implements LotFactureAssurancePort
{
    public function __construct(private readonly LotFactureAssuranceService $lotService)
    {
    }

    public function getDashboard(): array
    {
        return $this->lotService->getDashboard();
    }

    public function listLots(string $assuranceCode, ?string $statut = null): array
    {
        return $this->lotService->listLots($assuranceCode, $statut);
    }

    public function getLot(int $lotId): array
    {
        return $this->lotService->getLot($lotId);
    }

    public function openLot(
        string $assuranceCode,
        ?string $description = null,
        ?DateTimeInterface $dateDebut = null,
        ?DateTimeInterface $dateFin = null,
    ): array {
        return $this->lotService->openLot($assuranceCode, $description, $dateDebut, $dateFin);
    }

    public function updateLot(
        int $lotId,
        ?string $description = null,
        ?DateTimeInterface $dateDebut = null,
        ?DateTimeInterface $dateFin = null,
    ): array {
        return $this->lotService->updateLot($lotId, $description, $dateDebut, $dateFin);
    }

    public function sendLot(int $lotId): array
    {
        return $this->lotService->sendLot($lotId);
    }

    public function reopenLot(int $lotId): array
    {
        return $this->lotService->reopenLot($lotId);
    }

    public function confirmLot(int $lotId): array
    {
        return $this->lotService->confirmLot($lotId);
    }

    public function unconfirmLot(int $lotId): array
    {
        return $this->lotService->unconfirmLot($lotId);
    }

    public function refundLot(
        int $lotId,
        int $modeId,
        ?float $amount = null,
        ?DateTimeInterface $date = null,
    ): array {
        return $this->lotService->refundLot($lotId, $modeId, $amount, $date);
    }

    public function cancelRefund(int $lotId, int $transactionId, ?string $comment = null): array
    {
        return $this->lotService->cancelRefund($lotId, $transactionId, $comment);
    }

    public function cancelLotRecovery(int $lotId, ?string $comment = null): array
    {
        return $this->lotService->cancelLotRecovery($lotId, $comment);
    }

    public function addClaimToLot(int $lotId, int $factureId): array
    {
        return $this->lotService->addClaimToLot($lotId, $factureId);
    }

    public function removeClaimFromLot(int $lotId, int $factureId): array
    {
        return $this->lotService->removeClaimFromLot($lotId, $factureId);
    }

    public function moveClaimToLot(int $factureId, int $targetLotId): array
    {
        return $this->lotService->moveClaimToLot($factureId, $targetLotId);
    }
}
