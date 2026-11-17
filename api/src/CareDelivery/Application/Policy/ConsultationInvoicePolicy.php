<?php

namespace App\CareDelivery\Application\Policy;

use App\Billing\Domain\Entity\Facture;

final class ConsultationInvoicePolicy
{
    public function isModifiable(?Facture $facture): bool
    {
        if (!$facture) {
            return false;
        }

        if ($facture->getPaiements()->count() > 0) {
            return false;
        }

        $consultation = $facture->getConsultation();
        $factureAssurance = $consultation?->getFactureAssurance();
        if ($factureAssurance === null) {
            return true;
        }

        $lot = $factureAssurance->getLotFactureAssurance();
        if ($lot !== null) {
            $statut = $lot->getStatut() === 'recouvre' ? 'rembourse' : $lot->getStatut();
            if (in_array($statut, ['envoye', 'confirme', 'partiellement_rembourse', 'rembourse'], true)) {
                return false;
            }
        }

        return $factureAssurance->computePatientPaidAmount() <= 0;
    }
}
