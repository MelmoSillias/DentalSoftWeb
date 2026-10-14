import { buildFactureContextMenuItems, computeFactureStatus } from '@/utils/factureRow';

export const isConsultationClosed = (consultation) => Number(consultation?.state ?? consultation?.statut) === 1;

export const consultationHasInvoice = (consultation) => Number(consultation?.factureId) > 0;

export const consultationInvoiceStatus = (consultation) => {
    if (!consultationHasInvoice(consultation)) {
        return { label: 'Aucune facture', severity: 'contrast' };
    }

    return computeFactureStatus({
        montant: consultation.factureMontant,
        reste: consultation.factureReste,
        isRegle: Boolean(consultation.factureReglee),
        insuranceStatus: consultation.factureInsuranceStatus || null
    });
};

export const consultationToFactureRow = (consultation) => {
    if (!consultationHasInvoice(consultation)) return null;

    const isInsurance = consultation.factureType === 'FactureAssurance';
    const factureId = Number(consultation.factureId);

    return {
        id: factureId,
        factureAssuranceId: isInsurance ? Number(consultation.factureAssuranceId || factureId) : null,
        consultation: consultation.id,
        date: consultation.createdAt,
        montant: Number(consultation.factureMontant) || 0,
        reste: Number(consultation.factureReste) || 0,
        isRegle: Boolean(consultation.factureReglee),
        type: isInsurance ? 'FactureAssurance' : 'Facture',
        patientId: consultation.patientId,
        patient: consultation.patient,
        insuranceStatus: consultation.factureInsuranceStatus || null,
        insurance: isInsurance
            ? {
                  hasInsurance: true,
                  factureAssuranceId: Number(consultation.factureAssuranceId || factureId),
                  assuranceNom: consultation.assuranceNom || null,
                  insuranceRate: Number(consultation.insuranceRate) || 0,
                  insuranceAmount: Number(consultation.insuranceAmount) || 0,
                  patientPaidAmount: Number(consultation.patientPaidAmount) || 0,
                  restePatient: Number(consultation.factureReste) || 0,
                  insuranceStatus: consultation.factureInsuranceStatus || 'pending'
              }
            : { hasInsurance: false, insuranceStatus: 'none' }
    };
};

export const findFactureForConsultation = (consultation, factures = []) => {
    const consultationId = consultation?.id;
    if (!consultationId) return null;

    return (factures || []).find((facture) => Number(facture.consultation) === Number(consultationId)) ?? null;
};

/**
 * Build PrimeVue ContextMenu / Menu model for consultation actions in patient dossier.
 * @param {object} consultation
 * @param {{
 *   onDetails?: (consultation) => void,
 *   onCancel?: (consultation) => void,
 *   onEditInvoice?: (consultation) => void,
 *   onPayFacture?: (facture) => void,
 *   onPreviewFacture?: (facture) => void,
 *   onPrintFacture?: (facture) => void
 * }} handlers
 * @param {{ canModifyInvoice?: boolean, factures?: object[] }} options
 */
export const buildConsultationContextMenuItems = (consultation, handlers = {}, options = {}) => {
    if (!consultation) {
        return [
            {
                label: 'Aucune consultation',
                icon: 'pi pi-inbox',
                disabled: true
            }
        ];
    }

    const items = [];

    items.push({
        label: 'Voir détails',
        icon: 'pi pi-eye',
        command: () => handlers.onDetails?.(consultation)
    });

    if (!isConsultationClosed(consultation)) {
        items.push({
            label: 'Annuler',
            icon: 'pi pi-times',
            command: () => handlers.onCancel?.(consultation)
        });
    }

    if (isConsultationClosed(consultation) && consultation.factModifiable && options.canModifyInvoice) {
        items.push({
            label: 'Éditer facture',
            icon: 'pi pi-file-edit',
            command: () => handlers.onEditInvoice?.(consultation)
        });
    }

    const linkedFacture = findFactureForConsultation(consultation, options.factures);
    if (linkedFacture) {
        const factureItems = buildFactureContextMenuItems(linkedFacture, {
            onPay: (row) => handlers.onPayFacture?.(row),
            onPreview: (row) => handlers.onPreviewFacture?.(row),
            onPrint: (row) => handlers.onPrintFacture?.(row)
        });

        factureItems.forEach((item) => {
            if (!item.disabled) {
                items.push(item);
            }
        });
    }

    if (!items.length) {
        return [
            {
                label: 'Aucune action disponible',
                icon: 'pi pi-info-circle',
                disabled: true
            }
        ];
    }

    return items;
};
