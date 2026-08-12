<?php

namespace App\CareDelivery\Application\Port;

interface ConsultationWritePort
{
    /**
     * @param array<string, mixed> $payload
     */
    public function clotureConsultation(
        int $ficheId,
        int $consultationId,
        ?object $user,
        bool $restrictToMedecin,
        array $payload = [],
    ): void;

    /**
     * @param array<string, mixed> $data
     */
    public function updateConsultation(
        int $ficheId,
        int $consultationId,
        array $data,
        ?object $user,
        bool $restrictToMedecin,
    ): void;

    public function deleteConsultation(int $id, ?object $actor = null): bool;

    /**
     * @return array{ficheId: int, consultationId: int, created: bool}
     */
    public function linkOrCreateFiche(
        int $consultationId,
        ?int $ficheId,
        ?object $user,
        bool $restrictToMedecin,
        bool $forceCreate = false,
        bool $allowDuplicate = false,
    ): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    public function updateOrdonnance(int $ordonnanceId, array $payload): ?array;

    /**
     * @param list<array<string, mixed>> $lignes
     *
     * @return array<string, mixed>
     */
    public function updateFactureLines(
        int $consultationId,
        array $lignes,
        ?string $date = null,
        ?string $time = null,
    ): array;

    public function verifyMedecinPassword(int $consultationId, string $plainPassword): bool;
}
