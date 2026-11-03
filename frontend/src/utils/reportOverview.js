const number = (value) => Number(value ?? 0);
const money = (value) => `${number(value).toLocaleString('fr-FR')} FCFA`;

export function overviewFinancials(kpi = {}, services = {}) {
    const billed = number(kpi.totalApport) + number(services.facture);
    const insurance = number(kpi.totalPartAssurance) + number(services.partAssurance);
    return {
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
        { key: 'billing', label: 'Montant facturé sur la période', value: money(stats.billedTotal), icon: 'pi pi-file', tone: 'indigo',
            note: 'Consultations et services réalisés sur la période. Les parts patient et assurance forment le total.',
            subStats: [
                { label: 'Part facturée aux patients', value: money(stats.normalBilledTotal) },
                { label: 'Part facturée aux assurances', value: money(stats.insuranceBilledTotal) },
                { label: 'Dont services cabinet', value: money(cabinet.facture) },
                { label: 'Paiements patients sur ces prestations, reçus dans la période', value: money(stats.collectedCurrentActivities) },
                { label: 'Part patient non encaissée dans la période', value: money(stats.patientRemainingPeriod) },
                { label: 'Dont consultations et actes médicaux', value: money(number(stats.billedTotal) - number(cabinet.facture)) }
            ] },
        { key: 'treasury', label: 'Variation nette de trésorerie', value: money(stats.resultTotal), icon: 'pi pi-wallet', tone: number(stats.resultTotal) < 0 ? 'rose' : 'emerald',
            note: 'Transactions validées uniquement, selon leur date de transaction. Les entrées peuvent inclure des recettes manuelles.',
            subStats: [
                { label: 'Entrées de trésorerie validées', value: money(stats.revenueTotal) },
                { label: 'Sorties de trésorerie validées', value: money(stats.expenseTotal) },
                { label: 'Dont remboursements assurance reçus', value: money(stats.insuranceRefundsCollected) }
            ] },
        { key: 'services', label: 'Services cabinet facturés', value: money(cabinet.facture), icon: 'pi pi-building', tone: 'cyan',
            note: 'Même méthode que le rapport médecin : le reste compare la part patient facturée et les paiements de la période. Les paiements ultérieurs ne le réduisent pas.',
            subStats: [
                { label: 'Prestations réalisées', value: cabinet.count ?? 0 },
                { label: 'Part patient', value: money(cabinet.partPatient) },
                { label: 'Part assurance', value: money(cabinet.partAssurance) },
                { label: 'Encaissé sur les prestations de la période', value: money(cabinet.encaissePrestationsPeriode) },
                { label: 'Encaissé sur les prestations hors période', value: money(cabinet.encaisseHorsPeriode) },
                { label: 'Part patient non encaissée dans la période', value: money(cabinet.reste) }
            ] },
        { key: 'collections', label: 'Paiements patients reçus', value: money(stats.collectedTotal), icon: 'pi pi-credit-card', tone: 'emerald',
            note: 'Tous modes de paiement. Les remboursements des assureurs sont présentés séparément.',
            subStats: [
                { label: 'Sur les prestations de la période', value: money(stats.collectedCurrentActivities) },
                { label: 'Sur les prestations hors période', value: money(stats.collectedOtherActivities) },
                { label: 'Dont services cabinet', value: money(cabinet.encaisse) }
            ] },
        { key: 'patients', label: 'Patients distincts reçus', value: stats.uniquePatientsSeen ?? 0, icon: 'pi pi-users', tone: 'blue',
            note: 'Un patient est compté une seule fois dans la période. « Déjà inscrits » signifie inscrits avant son début.',
            subStats: [
                { label: 'Nouvelles inscriptions', value: stats.patientsNew ?? 0 },
                { label: 'Patients nouvellement inscrits et reçus', value: stats.newPatientsSeen ?? 0 },
                { label: 'Patients déjà inscrits et reçus', value: stats.returningPatientsSeen ?? 0 },
                { label: 'Consultations supplémentaires des mêmes patients', value: stats.repeatConsultations ?? 0 },
                { label: 'Dossiers patients actifs à ce jour', value: stats.patientsTotal ?? 0 }
            ] },
        { key: 'insurance', label: 'Remboursements assurance reçus', value: money(stats.insuranceRefundsCollected), icon: 'pi pi-shield', tone: 'violet',
            note: 'Les remboursements suivent la date de transaction. Le solde à recouvrer est un état actuel, toutes périodes, tenant compte des remboursements partiels des lots.',
            subStats: [
                { label: 'Part assurance facturée sur la période', value: money(stats.insuranceBilledTotal) },
                { label: 'Assurance à recouvrer à ce jour — toutes périodes', value: money(stats.insuranceOutstandingCurrent) }
            ] },
        { key: 'payroll', label: 'Rémunérations calculées', value: money(stats.payrollTotal), icon: 'pi pi-money-bill', tone: 'orange',
            note: 'La part assurance médecin est rémunérée sans attendre son remboursement. Ces montants ne sont pas des salaires effectivement versés. Le fixe est une valeur contractuelle non proratisée.',
            subStats: [
                { label: 'Fixe contractuel des employés actifs', value: money(stats.payrollFixed) },
                { label: 'Variable calculé sur la période', value: money(stats.payrollPercentage) }
            ] },
        { key: 'users', label: 'Utilisateurs par rôle', value: stats.usersTotal ?? 0, icon: 'pi pi-id-card', tone: 'teal',
            note: 'Somme des effectifs par rôle ; un compte cumulant plusieurs rôles peut être compté plusieurs fois.',
            subStats: [
                { label: 'Administrateurs', value: stats.usersAdmin ?? 0 },
                { label: 'Réceptionnistes', value: stats.usersReceptionist ?? 0 },
                { label: 'Médecins', value: stats.usersDoctor ?? 0 }
            ] },
        { key: 'employees', label: 'Employés actifs', value: stats.employeesTotal ?? 0, icon: 'pi pi-briefcase', tone: 'violet' },
        { key: 'rooms', label: 'Salles enregistrées', value: stats.consultRoomsCount ?? 0, icon: 'pi pi-building', tone: 'cyan' },
        { key: 'consumables', label: 'Références de consommables', value: stats.consumablesCount ?? 0, icon: 'pi pi-box', tone: 'orange' }
    ].sort((a, b) => (b.subStats?.length ?? 0) - (a.subStats?.length ?? 0));
}
