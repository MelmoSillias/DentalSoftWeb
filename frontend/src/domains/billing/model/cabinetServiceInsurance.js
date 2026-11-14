export const cabinetServiceInsuranceLabel = (service) => {
    if (!service?.coveredByInsurance) return '';
    if (service.statut === 'annule') return 'Prise en charge annulée';
    const insurance = service.facture?.insurance;
    if (insurance?.insuranceStatus === 'rembourse' || insurance?.lotStatut === 'rembourse') return 'Assurance remboursée';
    if (insurance?.lotStatut === 'partiellement_rembourse') return 'Assurance partiellement remboursée';
    if (insurance?.insuranceStatus === 'in_lot') return 'Assurance en lot';
    return 'Assurance à recouvrer';
};
