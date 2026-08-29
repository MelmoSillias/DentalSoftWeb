<?php

namespace App\CareDelivery\Service;

use App\Billing\Entity\FactureAssurance;
use App\CareDelivery\Entity\ActeMedical;
use App\CareDelivery\Entity\Consultation;

class ActAttributionService
{
    public const ATTRIBUTION_MEDECIN = 'medecin';
    public const ATTRIBUTION_CABINET = 'cabinet';

    /**
     * @return array{
     *     medecinActs: float,
     *     cabinetActs: float,
     *     medecinBillable: float,
     *     cabinetBillable: float,
     *     totalBillable: float,
     *     medecinRatio: float,
     *     cabinetRatio: float,
     *     medecinLabels: string[],
     *     cabinetLabels: string[]
     * }
     */
    public function splitConsultationAmounts(
        Consultation $consultation,
        bool $includeActs = true,
        float $consultationFeeForMedecin = 0.0,
    ): array {
        $actsSplit = $this->splitActs($consultation, $includeActs);

        $medecinBillable = max(0.0, $consultationFeeForMedecin) + $actsSplit['medecinActs'];
        $cabinetBillable = $actsSplit['cabinetActs'];
        $totalBillable = $medecinBillable + $cabinetBillable;

        return [
            'medecinActs' => $actsSplit['medecinActs'],
            'cabinetActs' => $actsSplit['cabinetActs'],
            'medecinBillable' => $medecinBillable,
            'cabinetBillable' => $cabinetBillable,
            'totalBillable' => $totalBillable,
            'medecinRatio' => $this->ratio($medecinBillable, $totalBillable),
            'cabinetRatio' => $this->ratio($cabinetBillable, $totalBillable),
            'medecinLabels' => $actsSplit['medecinLabels'],
            'cabinetLabels' => $actsSplit['cabinetLabels'],
        ];
    }

    public function allocateAmount(float $amount, float $medecinBillable, float $totalBillable): array
    {
        if ($amount <= 0.0 || $totalBillable <= 0.0) {
            return ['medecin' => 0.0, 'cabinet' => 0.0];
        }

        $medecinShare = round($amount * $this->ratio($medecinBillable, $totalBillable), 2);
        $cabinetShare = round($amount - $medecinShare, 2);

        return [
            'medecin' => $medecinShare,
            'cabinet' => $cabinetShare,
        ];
    }

    /**
     * @return array{medecinActs: float, cabinetActs: float, medecinLabels: string[], cabinetLabels: string[]}
     */
    private function splitActs(Consultation $consultation, bool $includeActs): array
    {
        $medecinActs = 0.0;
        $cabinetActs = 0.0;
        $medecinLabels = [];
        $cabinetLabels = [];

        if (!$includeActs) {
            return [
                'medecinActs' => 0.0,
                'cabinetActs' => 0.0,
                'medecinLabels' => [],
                'cabinetLabels' => [],
            ];
        }

        foreach ($consultation->getActes() as $acte) {
            if (!$acte instanceof ActeMedical) {
                continue;
            }

            $lineTotal = (float) (($acte->getPrix() ?? 0) * max(1, (int) ($acte->getQuantite() ?? 1)));
            $label = $this->actLabel($acte);

            if ($acte->isCabinetService()) {
                $cabinetActs += $lineTotal;
                if ($label !== '') {
                    $cabinetLabels[] = $label;
                }
                continue;
            }

            $medecinActs += $lineTotal;
            if ($label !== '') {
                $medecinLabels[] = $label;
            }
        }

        return [
            'medecinActs' => $medecinActs,
            'cabinetActs' => $cabinetActs,
            'medecinLabels' => $medecinLabels,
            'cabinetLabels' => $cabinetLabels,
        ];
    }

    private function resolveConsultationFeeAmount(Consultation $consultation): float
    {
        $factureAssurance = $consultation->getFactureAssurance();
        if ($factureAssurance instanceof FactureAssurance && $factureAssurance->isConsultationPayante()) {
            return (float) $factureAssurance->getConsultationAmount();
        }

        return 0.0;
    }

    public function resolveInsuranceConsultationFee(Consultation $consultation): float
    {
        return $this->resolveConsultationFeeAmount($consultation);
    }

    private function actLabel(ActeMedical $acte): string
    {
        $label = trim((string) ($acte->getType() ?? ''));
        if ($label === '') {
            $label = trim((string) ($acte->getDescription() ?? ''));
        }

        return $label;
    }

    private function ratio(float $part, float $total): float
    {
        if ($total <= 0.0 || $part <= 0.0) {
            return 0.0;
        }

        return $part / $total;
    }
}
