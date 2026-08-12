<?php

namespace App\CareDelivery\Application\Port;

interface ConsultationClinicalRecordPort
{
    public function getFicheById(int $ficheId): object;

    public function findLastFicheForPatient(object $patient): ?object;

    /**
     * @return array{
     *     ficheMedicale: ?object,
     *     fiche: ?object,
     *     ficheId: ?int,
     *     hasFiche: bool,
     *     lastFicheId: ?int,
     *     motif: string
     * }
     */
    public function resolvePendingFicheData(object $consultation): array;

    /**
     * @return array{0: object, 1: bool}
     */
    public function resolveFicheForConsultation(
        object $consultation,
        ?int $ficheId = null,
        bool $forceCreate = false,
        bool $allowDuplicate = false,
    ): array;

    public function lockPatientForFicheResolution(object $patient): void;

    public function getFicheConsultationPayload(object $fiche, object $consultation): array;
}
