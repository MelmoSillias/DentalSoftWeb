<?php

namespace App\Dto\Focus;

final class FocusReceptionPayloadDto
{
    /** @param FocusReceptionConsultationDto[] $consultations */
    /** @param FocusReceptionPatientDto[] $recentPatients */
    /** @param array<string, FocusReceptionBillingDto> $billingByConsultation */
    public function __construct(
        private array $consultations,
        private array $recentPatients,
        private array $billingByConsultation,
    ) {
    }

    public function toArray(): array
    {
        $billing = [];
        foreach ($this->billingByConsultation as $consultationId => $dto) {
            $billing[(string) $consultationId] = $dto->toArray();
        }

        return [
            'consultations' => array_map(static fn (FocusReceptionConsultationDto $consultation) => $consultation->toArray(), $this->consultations),
            'recentPatients' => array_map(static fn (FocusReceptionPatientDto $patient) => $patient->toArray(), $this->recentPatients),
            'billingByConsultation' => $billing,
        ];
    }
}