/**
 * Shared invoice row helpers (Caisse + Dossier patient).
 */

import { isInsuranceFactureRow } from '@/composables/usePayTabsDialog';

export { isInsuranceFactureRow };

export const isValidatedEmptyFacture = (row) => row?.isRegle && Number(row?.montant) === 0 && Number(row?.reste) === 0;

export const computePriorReliquat = (row) => {
    const prior = Number(row?.priorReliquat);
    if (Number.isFinite(prior) && prior > 0) {
        return prior;
    }
    const total = Number(row?.patientImpayees);
    const reste = Number(row?.reste) || 0;
    if (Number.isFinite(total) && total > 0) {
        return Math.max(0, total - Math.round(reste));
    }
    return 0;
};

export const hasPatientReliquat = (row) => computePriorReliquat(row) > 0;

export const computeFactureStatus = (row) => {
    const montant = Number(row?.montant) || 0;
    const reste = Number(row?.reste) || 0;

    if (row?.isRegle && reste === 0) {
        if (isValidatedEmptyFacture(row) || row?.insuranceStatus === 'validated_empty') {
            return { label: 'Validée', severity: 'info' };
        }
        return { label: 'Payé', severity: 'success' };
    }
    if (!row?.isRegle && reste === 0) return { label: 'Vide non validé', severity: 'secondary' };
    if (reste === montant) return { label: 'Impayé', severity: 'danger' };
    return { label: 'Partiellement payé', severity: 'warning' };
};

export const canPreviewFacture = (row) => row?.insurance?.hasInsurance === true || !(Number(row?.montant) === 0 && Number(row?.reste) === 0);

export const targetIsFreeFacture = (row) => !row?.isRegle && Number(row?.reste) === 0;

export const canSettleFacture = (row) => Boolean(row) && (!row?.isRegle || hasPatientReliquat(row));

/** @deprecated Use canSettleFacture */
export const canPayFacture = (row) => canSettleFacture(row);

export const canModifyFacture = (row, { allowInvoiceModification = false } = {}) =>
    allowInvoiceModification && !isInsuranceFactureRow(row) && !row?.hasPayments && (isValidatedEmptyFacture(row) || (Number(row?.montant) === Number(row?.reste) && !row?.isRegle));

export const isUnpaidFacture = (row) => {
    const status = computeFactureStatus(row).label;
    return status === 'Impayé' || status === 'Partiellement payé';
};

/**
 * Build PrimeVue ContextMenu / Menu model for invoice actions.
 * @param {object} row
 * @param {{ onPay?: (row) => void, onPreview?: (row) => void, onPrint?: (row) => void }} handlers
 */
export const buildFactureContextMenuItems = (row, handlers = {}) => {
    if (!row) {
        return [
            {
                label: 'Aucune facture',
                icon: 'pi pi-inbox',
                disabled: true
            }
        ];
    }

    const items = [];

    if (canSettleFacture(row)) {
        const free = targetIsFreeFacture(row);
        items.push({
            label: free ? 'Valider' : 'Payer',
            icon: free ? 'pi pi-check' : 'pi pi-wallet',
            command: () => handlers.onPay?.(row)
        });
    }

    if (canPreviewFacture(row)) {
        items.push({
            label: 'Voir',
            icon: 'pi pi-eye',
            command: () => handlers.onPreview?.(row)
        });
        items.push({
            label: 'Imprimer',
            icon: 'pi pi-print',
            command: () => handlers.onPrint?.(row)
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

export const formatFactureFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;
