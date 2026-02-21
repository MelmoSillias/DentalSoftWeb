$(document).ready(function () {
    loadDashboardData();
    initDateRangePicker();
});

function loadDashboardData(from = null, to = null) {
    $.getJSON('/api/report/medecin', { from, to }, function (data) {
        // Nom du médecin
        $('#doctorName').text(data.nom);

        // Statistiques globales
        $('#patientsTotal').text(data.stats.patientsTotal);
        $('#totalConsultations').text(data.stats.totalConsultations);
        $('#pendingConsultations').text(data.stats.consultationsEnAttente);
        $('#todayAppointments').text(data.stats.rdvJour);

        // Détails RH
        $('#nom').val(data.nom);
        $('#prenom').val(data.prenom);
        $('#matricule').val(data.matricule);
        $('#fonction').val(data.fonction);
        $('#telephone').val(data.telephone);
        $('#email').val(data.email);
        $('#typeSelect').val(data.type);
        $('#typeHidden').val(data.type);
        $('#dateEmbauche').val(data.dateEmbauche);
        $('#typeSalaire').val(data.typeSalaire);
        $('#valeurSalaire').val(data.valeurSalaire);
        $('#typeContrat').val(data.typeContrat);
        $('#dureeContrat').val(data.dureeContrat);
        $('#employedSince').text(formatDateDisplay(data.dateEmbauche));
        $('#salaryType').text(formatSalaire(data.typeSalaire, data.valeurSalaire));

        // Jours travaillés
        if (Array.isArray(data.joursTravailles)) {
            data.joursTravailles.forEach(jour => {
                $(`input[name="comingDays[]"][value="${jour}"]`).prop('checked', true);
            });
        }

        // Consultations
        $('#freeConsultations').text(data.period.freeConsultations);
        $('#paidConsultations').text(data.period.paidConsultations);
        $('#AmountConsultations').text(formatFcfa(parseInt(data.period.paidConsultations) * 5000));

        // RDV stats
        $('#scheduledAppointments').text(data.period.rdvPlanifies);
        $('#pendingAppointments').text(data.period.rdvEnAttente);
        $('#confirmedAppointments').text(data.period.rdvValides);
        $('#postponedAppointments').text(data.period.rdvReportes);
        $('#cancelledAppointments').text(data.period.rdvAnnules);

        // Apport
        $('#periodRevenueC').text(formatFcfa(parseInt(data.period.paidConsultations) * 5000));
        $('#periodRevenueA').text(formatFcfa(parseFloat(data.period.apportTotal) - (parseInt(data.period.paidConsultations) * 5000)));
        $('#periodRevenueT').text(formatFcfa(data.period.apportTotal));

        // Actes médicaux
        renderMedicalActs(data.period.paiements_period);
    });
}

function renderMedicalActs(paiements_period) {
    const $list = $('#medicalActsList').empty();

    if (!paiements_period || paiements_period.length === 0) {
        $list.append(`<li class="list-group-item text-muted">Aucun acte posé durant cette période.</li>`);
        return;
    }

    paiements_period.forEach(paiement => {
        $list.append(`
            <li class="list-group-item d-flex justify-content-between flex-column">
                <strong>${paiement.description}</strong>
                <small>${paiement.patient} – ${paiement.date}</small>
                <span class="badge bg-secondary align-self-end text-white">${formatFcfa(paiement.montant_paye)}</span>
            </li>
        `);
    });
}

function initDateRangePicker() {
    const $daterange = $('<input type="text" class="form-control form-control-sm" id="periodPicker" style="max-width: 220px;" />');
    const $container = $('<div class="d-flex justify-content-end mb-2"></div>').append($daterange);
    $('#globalStats').before($container);

    const start = moment().startOf('month');
    const end = moment();

    function cb(start, end) {
        $daterange.val(start.format('YYYY-MM-DD') + ' au ' + end.format('YYYY-MM-DD'));
        loadDashboardData(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
    }

    $daterange.daterangepicker({
        startDate: start,
        endDate: end,
        locale: {
            format: 'YYYY-MM-DD',
            separator: ' au ',
            applyLabel: "Appliquer",
            cancelLabel: "Annuler",
            fromLabel: "Du",
            toLabel: "Au",
            daysOfWeek: ["Dim", "Lun", "Mar", "Mer", "Jeu", "Ven", "Sam"],
            monthNames: [
                "Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
                "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"
            ],
            firstDay: 1
        }
    }, cb);

    cb(start, end);
}

// Formatage
function formatFcfa(val) {
    return Number(val).toLocaleString('fr-FR') + ' Fcfa';
}

function formatDateDisplay(dateStr) {
    if (!dateStr) return '--';
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long' });
}

function formatSalaire(type, valeur) {
    if (type === 'pourcentage') {
        return `Pourcentage (${valeur}%)`;
    }
    return `Fixe (${formatFcfa(valeur)})`;
}
