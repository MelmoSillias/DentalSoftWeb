const number = (value) => Number(value ?? 0);
const countAmount = (count, amount) => `${number(count)} · ${money(amount)}`;
const money = (value) => `${number(value).toLocaleString('fr-FR')} FCFA`;

export function overviewFinancials(kpi = {}, services = {}) {
    const billed = number(kpi.totalApport) + number(services.facture);
    const insurance = number(kpi.totalPartAssurance) + number(services.partAssurance);
    return {
        billingBreakdown: kpi.billingBreakdown || {},
        billedTotal: billed,
        normalBilledTotal: billed - insurance,
        insuranceBilledTotal: insurance,
        collectedTotal: number(kpi.totalPaidCash ?? kpi.totalPaid) + number(services.encaisse),
        collectedCurrentActivities: number(kpi.totalCollectedCurrentActivities) + number(services.encaissePrestationsPeriode),
        collectedOtherActivities: number(kpi.totalCollectedPriorActivities) + number(services.encaisseHorsPeriode),
        patientRemainingPeriod: number(kpi.totalPatientRemaining) + number(services.reste)
    };
}

export function overviewCards(stats = {}) {
    const cabinet = stats.servicesCabinet || {};
    return [
        { key: 'billing', label: 'Facturation', value: money(stats.billedTotal), icon: 'pi pi-file', tone: 'indigo',
            subStats: [
                { label: 'Factures normales', value: countAmount(stats.billingBreakdown?.normalCount, stats.billingBreakdown?.normalAmount) },
                { label: 'Factures assurées', value: countAmount(stats.billingBreakdown?.insuredCount, stats.billingBreakdown?.insuredAmount) },
                { label: 'Prestations du cabinet', value: countAmount(cabinet.count, cabinet.facture) },
                { label: 'Prestations non assurées', value: countAmount(cabinet.normalCount, cabinet.normalAmount) },
                { label: 'Prestations assurées', value: countAmount(cabinet.insuredCount, cabinet.insuredAmount) },
                { label: 'Prestations · part patient', value: money(cabinet.partPatient) },
                { label: 'Prestations · part assurance', value: money(cabinet.partAssurance) },
                { label: 'Part patient totale', value: money(stats.normalBilledTotal) },
                { label: 'Part assurance totale', value: money(stats.insuranceBilledTotal) }
            ] },
        { key: 'collections', label: 'Encaissements', value: money(stats.collectedTotal), icon: 'pi pi-credit-card', tone: 'emerald',
            subStats: [
                // Total des encaissements patients et du cabinet : le libellé précédent était trop général.
                { label: 'Total encaissé', value: money(stats.collectedTotal) },
                // Encaissements des soins réalisés sur la période, hors prestations du cabinet.
                { label: 'Soins · période', value: money(number(stats.collectedCurrentActivities) - number(cabinet.encaissePrestationsPeriode)) },
                // Encaissements de la période portant sur des soins de périodes antérieures.
                { label: 'Reliquats encaissés sur soins', value: money(number(stats.collectedOtherActivities) - number(cabinet.encaisseHorsPeriode)) },
                // Encaissements des prestations du cabinet réalisées sur la période.
                { label: 'Prestations du cabinet · période', value: money(cabinet.encaissePrestationsPeriode) },
                // Encaissements reçus sur la période pour des prestations antérieures du cabinet.
                { label: 'Reliquats encaissés sur prestations du cabinet', value: money(cabinet.encaisseHorsPeriode) },
                // Reste dû par les patients pour les prestations de la période.
                { label: 'Impayés patients · période', value: money(stats.patientRemainingPeriod) },
                // Part du reste dû correspondant aux soins, hors prestations du cabinet.
                { label: '↳ Dont soins', value: money(number(stats.patientRemainingPeriod) - number(cabinet.reste)) },
                { label: '↳ Dont prestations du cabinet', value: money(cabinet.reste) }
            ], note: 'Impayés patients : prestations de la période, après paiements reçus sur cette période.' },
        { key: 'insurance', label: 'Remboursements assurance', value: money(stats.insuranceRefundsCollected), icon: 'pi pi-shield', tone: 'violet',
            subStats: [
                { label: 'À recouvrer · actuel', value: money(stats.insuranceOutstandingCurrent) }
            ] },
        { key: 'treasury', label: 'Trésorerie nette', value: money(stats.resultTotal), icon: 'pi pi-wallet', tone: number(stats.resultTotal) < 0 ? 'rose' : 'emerald',
            subStats: [
                { label: 'Entrées validées', value: money(stats.revenueTotal) },
                { label: 'Sorties validées', value: money(stats.expenseTotal) }
            ] },
        { key: 'patients', label: 'Patients reçus', value: stats.uniquePatientsSeen ?? 0, icon: 'pi pi-users', tone: 'blue',
            subStats: [
                { label: 'Nouvelles inscriptions', value: stats.patientsNew ?? 0 },
                { label: 'Nouveaux patients reçus', value: stats.newPatientsSeen ?? 0 },
                { label: 'Anciens patients reçus', value: stats.returningPatientsSeen ?? 0 },
                { label: 'Visites supplémentaires', value: stats.repeatConsultations ?? 0 },
                { label: 'Dossiers actifs · actuel', value: stats.patientsTotal ?? 0 }
            ] },
        { key: 'payroll', label: 'Rémunérations', value: money(stats.payrollTotal), icon: 'pi pi-money-bill', tone: 'orange',
            note: 'Montants calculés ; fixe non proratisé.',
            subStats: [
                { label: 'Fixe contractuel', value: money(stats.payrollFixed) },
                { label: 'Part variable', value: money(stats.payrollPercentage) }
            ] },
        { key: 'users', label: 'Utilisateurs par rôle', value: stats.usersTotal ?? 0, icon: 'pi pi-id-card', tone: 'teal',
            subStats: [
                { label: 'Administrateurs', value: stats.usersAdmin ?? 0 },
                { label: 'Réceptionnistes', value: stats.usersReceptionist ?? 0 },
                { label: 'Médecins', value: stats.usersDoctor ?? 0 }
            ] },
        { key: 'employees', label: 'Employés actifs', value: stats.employeesTotal ?? 0, icon: 'pi pi-briefcase', tone: 'violet' },
        { key: 'rooms', label: 'Salles', value: stats.consultRoomsCount ?? 0, icon: 'pi pi-building', tone: 'cyan' },
        { key: 'consumables', label: 'Consommables', value: stats.consumablesCount ?? 0, icon: 'pi pi-box', tone: 'orange' }
    ];
}
