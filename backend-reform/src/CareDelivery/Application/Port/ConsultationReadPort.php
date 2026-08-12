<?php

namespace App\CareDelivery\Application\Port;

interface ConsultationReadPort
{
    /**
     * @return array{entity: mixed, data: array<string, mixed>}
     */
    public function getConsultationDetailsData(int $consultationId): array;

    /**
     * @return array{consultation: mixed, actes: mixed}
     */
    public function getConsultationDetailsContext(int $consultationId): array;

    /**
     * Full JSON payload for /api/fiches/{ficheId}/consultations/{consultationId}/json
     *
     * @return array<string, mixed>
     */
    public function getFicheConsultationJson(
        int $ficheId,
        int $consultationId,
        ?object $user,
        bool $restrictToMedecin,
    ): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingConsultationsJsonForUser(?object $user, bool $restrictToMedecin): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getClosedConsultationsData(): array;

    /**
     * @return array{data: list<array<string, mixed>>}
     */
    public function consultationsDuJour(?string $date, ?object $user): array;

    /**
     * @return array<string, mixed>
     */
    public function getReceptionFocusData(?string $date, ?object $user): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrdonnances(int $consultationId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getOrdonnanceData(int $ordonnanceId): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function getFactureLines(int $consultationId): ?array;
}
