/**
 * Helpers for multi-invoice payment dialogs (Caisse + Focus).
 */

export const resolveFacturePatientId = (row) => {
    if (!row) return null;
    const fromFlat = Number(row.patientId ?? 0);
    if (fromFlat > 0) return fromFlat;
    if (row.patient && typeof row.patient === 'object') {
        const fromPatient = Number(row.patient.id ?? 0);
        if (fromPatient > 0) return fromPatient;
    }
    return null;
};

export const isInsuranceFactureRow = (row) => row?.type === 'FactureAssurance' || row?.type === 'assurance' || row?.insurance?.hasInsurance === true;

export const factureIdentityKeys = (row) => {
    const keys = new Set();
    const id = Number(row?.id ?? 0);
    if (id > 0) keys.add(`id:${id}`);
    const faId = Number(row?.factureAssuranceId ?? row?.insurance?.factureAssuranceId ?? 0);
    if (faId > 0) keys.add(`fa:${faId}`);
    const consultationId = Number(row?.consultation ?? row?.consultationId ?? 0);
    if (consultationId > 0) keys.add(`c:${consultationId}`);
    return keys;
};

export const sameFactureIdentity = (a, b) => {
    if (!a || !b) return false;
    const keysA = factureIdentityKeys(a);
    for (const key of factureIdentityKeys(b)) {
        if (keysA.has(key)) return true;
    }
    return false;
};

export const isEmptyUnvalidatedFacture = (row) => {
    if (!row || row.isRegle) return false;
    return Number(row.reste ?? 0) === 0;
};

export const isSettledFacture = (row) => Boolean(row?.isRegle) && Number(row?.reste ?? 0) === 0;

const resolvePrimaryPayTabMode = (primaryRow, options = {}) => {
    if (options.primaryMode) {
        return options.primaryMode;
    }
    if (isEmptyUnvalidatedFacture(primaryRow)) {
        return 'validate';
    }
    if (isSettledFacture(primaryRow)) {
        return 'settled';
    }
    return 'pay';
};

export const resolveOpenPayDialogMode = (row, primaryMode = null) => resolvePrimaryPayTabMode(row, { primaryMode });

export const formatPayTabLabel = (row, { isPrimary = false } = {}) => {
    const id = row?.id ?? '—';
    const date = row?.date ? String(row.date).slice(0, 10) : '';
    const base = date ? `Facture #${id} · ${date}` : `Facture #${id}`;
    return isPrimary ? `${base} (sélectionnée)` : base;
};

/**
 * Build pay tabs: primary invoice first, then other unpaid invoices.
 * @param {object} primaryRow
 * @param {object[]} unpaidRows
 * @param {{ primaryMode?: 'pay'|'validate'|'settled' }} options
 */
export const buildPayTabs = (primaryRow, unpaidRows = [], options = {}) => {
    if (!primaryRow) return [];

    const primaryMode = resolvePrimaryPayTabMode(primaryRow, options);

    const tabs = [
        {
            id: String(primaryRow.id),
            label: formatPayTabLabel(primaryRow, { isPrimary: true }),
            facture: primaryRow,
            mode: primaryMode,
            isPrimary: true
        }
    ];

    const others = (Array.isArray(unpaidRows) ? unpaidRows : []).filter((row) => row && !sameFactureIdentity(row, primaryRow) && Number(row.reste ?? 0) > 0);

    for (const row of others) {
        tabs.push({
            id: String(row.id),
            label: formatPayTabLabel(row),
            facture: row,
            mode: isEmptyUnvalidatedFacture(row) ? 'validate' : 'pay',
            isPrimary: false
        });
    }

    return tabs;
};

export const sumPriorReliquatFromTabs = (tabs, activeTabId) => {
    const list = Array.isArray(tabs) ? tabs : [];
    return list.reduce((sum, tab) => {
        if (String(tab.id) === String(activeTabId)) return sum;
        return sum + (Number(tab?.facture?.reste ?? 0) || 0);
    }, 0);
};

/**
 * After settling the active tab: remove it and pick the next one.
 * @returns {{ tabs: object[], nextTabId: string|null, shouldClose: boolean }}
 */
export const advanceAfterSettledTab = (tabs, settledTabId) => {
    const list = Array.isArray(tabs) ? tabs : [];
    const settledIndex = list.findIndex((tab) => String(tab.id) === String(settledTabId));
    const remaining = list.filter((tab) => String(tab.id) !== String(settledTabId));

    if (!remaining.length) {
        return { tabs: [], nextTabId: null, shouldClose: true };
    }

    const nextIndex = settledIndex >= 0 ? Math.min(settledIndex, remaining.length - 1) : 0;

    return {
        tabs: remaining,
        nextTabId: String(remaining[nextIndex].id),
        shouldClose: false
    };
};

export const applyPartialPaymentToTab = (tabs, tabId, paidAmount) => {
    const amount = Number(paidAmount) || 0;
    return (Array.isArray(tabs) ? tabs : []).map((tab) => {
        if (String(tab.id) !== String(tabId)) return tab;
        const prevReste = Number(tab.facture?.reste ?? 0) || 0;
        const nextReste = Math.max(0, prevReste - amount);
        const prevMontant = Number(tab.facture?.montant ?? tab.facture?.montantPatient ?? prevReste) || 0;
        const alreadyPaid = Number(tab.facture?.insurance?.patientPaidAmount ?? 0) || 0;
        const nextFacture = {
            ...tab.facture,
            reste: nextReste,
            hasPayments: true,
            insurance: tab.facture?.insurance
                ? {
                      ...tab.facture.insurance,
                      patientPaidAmount: alreadyPaid + amount,
                      patientRemainingAmount: nextReste,
                      restePatient: nextReste
                  }
                : tab.facture?.insurance
        };
        return {
            ...tab,
            facture: nextFacture,
            mode: nextReste <= 0 && prevMontant <= 0 ? 'validate' : nextReste <= 0 ? 'pay' : tab.mode
        };
    });
};
