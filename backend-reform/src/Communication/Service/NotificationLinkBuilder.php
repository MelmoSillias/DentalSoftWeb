<?php

namespace App\Communication\Service;

/**
 * Canonical frontend routes for notification deep links.
 * Keep in sync with frontend/src/router/index.js.
 */
final class NotificationLinkBuilder
{
    public const PATIENTS_LIST = '/patients/liste';
    public const AGENDA_RDV = '/agenda/rendez-vous';
    public const CONSULTATIONS_QUEUE = '/consultations/cards';
    public const CONSULTATIONS_TABLE = '/consultations/table';
    public const CAISSE = '/caisse';
    public const ADMIN_CONSUMABLES = '/administration/consommables';
    public const ADMIN_USERS = '/administration/utilisateurs';
    public const ADMIN_RH = '/administration/gestionrh';

    public static function patient(?int $patientId = null): string
    {
        if ($patientId !== null && $patientId > 0) {
            return sprintf('/patients/dossier/%d', $patientId);
        }

        return self::PATIENTS_LIST;
    }

    public static function consultation(?int $consultationId = null, bool $queue = false): string
    {
        if ($consultationId !== null && $consultationId > 0) {
            return sprintf('/consultations/form?id=%d&mode=continue', $consultationId);
        }

        return $queue ? self::CONSULTATIONS_QUEUE : self::CONSULTATIONS_TABLE;
    }
}
