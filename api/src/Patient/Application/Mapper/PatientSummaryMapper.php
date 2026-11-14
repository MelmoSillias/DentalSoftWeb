<?php

namespace App\Patient\Application\Mapper;

use App\CareDelivery\Domain\Entity\Consultation;
use App\Patient\Domain\Entity\Patient;


final class PatientSummaryMapper
{
    public function formatPatientSummary(Patient $patient, ?Consultation $consultation, int $impayees): array
    {
        $contact = $patient->getContactUrgence();
        $photo = $this->resolvePatientPhoto($patient);

        return [
            'id' => $patient->getId(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'fullname' => $patient->getFullName(),
            'photo' => $photo,
            'age' => $patient->getAge(),
            'dateNaissance' => $patient->getDateNaissance() ? $patient->getDateNaissance()->format('Y-m-d') : null,
            'sexe' => $patient->getSexe(),
            'telephone' => $patient->getTelephone(),
            'dateInscription' => $patient->getDateInscription() ? $patient->getDateInscription()->format('Y-m-d H:i') : null,
            'email' => $patient->getEmail(),
            'adresse' => $patient->getAdresse(),
            'profession' => $patient->getProfession(),
            'lieuNaissance' => $patient->getLieuNaissance(),
            'groupeSanguin' => $patient->getGroupeSanguin(),
            'referencement' => $patient->getReferencement() ?? '',
            'contactUrgence' => $contact ? [
                'nom' => $contact->getNom(),
                'telephone' => $contact->getTelephone(),
                'lienParente' => $contact->getLienParente(),
            ] : null,
            'smsPreferences' => $this->extractSmsPreferences($patient),
            'insuranceProfile' => $this->formatInsuranceProfile($patient),
            'derniereConsultation' => $consultation ? [
                'id' => $consultation->getId(),
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
                'motif' => $consultation->getNoteSeance(),
                'statut' => $consultation->getStatut(),
            ] : null,
            'impayees' => $impayees,
            'archiveFiles' => $patient->getArchiveFiles() ? array_map(fn (array $file) => [
                'nom' => $file['nom'] ?? 'Fichier',
                'url' => $file['url'] ?? null,
            ], $patient->getArchiveFiles()) : [],
        ];
    }


    public function resolvePatientPhoto(Patient $patient): ?string
    {
        $photo = $patient->getPhoto();

        return is_string($photo) && $photo !== '' ? $photo : null;
    }


    /** @return array<string, bool> */
    public function extractSmsPreferences(Patient $patient): array
    {
        return [
            'patientCreated' => $patient->isSmsPatientCreated(),
            'receipt' => $patient->isSmsReceipt(),
            'ticket' => $patient->isSmsTicket(),
            'invoice' => $patient->isSmsInvoice(),
            'appointmentReminder' => $patient->isSmsAppointmentReminder(),
            'unsubscribed' => $patient->isSmsUnsubscribed(),
            'blacklisted' => $patient->isSmsBlacklisted(),
        ];
    }

    public function formatInsuranceProfile(Patient $patient): ?array
    {
        $profile = $patient->getAssuranceProfile();
        if (!$profile) {
            return null;
        }

        $assurance = $profile->getAssurance();

        return [
            'id' => $profile->getId(),
            'enabled' => true,
            'coverageRate' => $profile->getCoverageRate(),
            'formData' => $profile->getFormData(),
            'assurance' => $assurance ? [
                'id' => $assurance->getId(),
                'code' => $assurance->getCode(),
                'nom' => $assurance->getNom(),
                'logoPath' => $assurance->getLogoPath(),
                'actif' => $assurance->isActif(),
            ] : null,
        ];
    }
}
