<?php

namespace App\Dto\Focus;

final class FocusReceptionPayloadDto
{
    /** @param FocusReceptionConsultationDto[] $consultations */
    /** @param FocusReceptionPatientDto[] $recentPatients */
    /** @param array<string, FocusReceptionBillingDto> $billingByConsultation */
    /** @param array<string, list<array{id: int, date: ?string, consultationId: int, montantPatient: float, reste: float, type: string, factureAssuranceId: ?int}>> $unpaidByPatientId */
    public function __construct(
        private array $consultations,
        private array $recentPatients,
        private array $billingByConsultation,
        private array $unpaidByPatientId = [],
    ) {
    }

    public function toArray(): array
    {
        $billing = [];
        foreach ($this->billingByConsultation as $consultationId => $dto) {
            $billing[(string) $consultationId] = $dto->toArray();
        }

        $unpaid = [];
        foreach ($this->unpaidByPatientId as $patientId => $rows) {
            $unpaid[(string) $patientId] = $rows;
        }

        return [
            'consultations' => array_map(static fn (FocusReceptionConsultationDto $consultation) => $consultation->toArray(), $this->consultations),
            'recentPatients' => array_map(static fn (FocusReceptionPatientDto $patient) => $patient->toArray(), $this->recentPatients),
            'billingByConsultation' => $billing,
            'unpaidByPatientId' => $unpaid,
        ];
    }
}
