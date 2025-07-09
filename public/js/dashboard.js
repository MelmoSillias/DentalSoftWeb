// public/js/dashboard.js

$(function() {
  // 1) Initialisation DateRangePicker avec période par défaut = mois courant
  const startOfMonth = moment().startOf('month');
  const endOfMonth = moment().endOf('month');

  $('#reportrange').daterangepicker({
    startDate: startOfMonth,
    endDate: endOfMonth,
    opens: 'right',
    locale: { format: 'DD/MM/YYYY', applyLabel: 'Appliquer', cancelLabel: 'Annuler' }
  }, function(start, end) {
    $('#reportrange span').text(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
    // rechargement des données à chaque changement de période
    loadAll(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
  });

  // Affiche la période par défaut dans le label
  $('#reportrange span').text(
    startOfMonth.format('DD/MM/YYYY') + ' - ' + endOfMonth.format('DD/MM/YYYY')
  );

  // Chargement initial avec la période du mois courant
  loadAll(startOfMonth.format('YYYY-MM-DD'), endOfMonth.format('YYYY-MM-DD'));
});

function loadAll(from = null, to = null) {
  loadGlobalStats(from, to);
  loadNonPeriodicDetails();
  loadLowStockConsumables();
  loadGlobalPatientsDetails();
  loadPeriodicPatients(from, to);
  loadPeriodicConsultations(from, to);
  loadPeriodicAppointments(from, to);
  loadPeriodicRoomUsage(from, to);
  loadPeriodicPaymentBalances(from, to);
  loadPeriodicPaymentFrequency(from, to);
  loadActsStats(from, to);
  loadDoctorReports(from, to);
}

// --- Helpers pour AJAX + mise à jour DOM ---

function loadGlobalStats(from, to) {
  $.getJSON('/api/dashboard/global-stats', { from, to }, data => {
    $('#patientsTotal').text(data.patientsTotal);
    $('#capitalTotal').text(formatFcfa(data.capitalTotal));
    // breakdown
    $('#capitalCash').text(formatFcfa(data.inCash)); 
    $('#revenueTotal').text(formatFcfa(data.revenueTotal));
    $('#appointmentsTotal').text(data.appointmentsTotal);
    $('#employeesTotal').text(data.employeesTotal);
    $('#payrollFixed').text(formatFcfa(data.payrollFixed));
    $('#payrollFixedCount').text(data.payrollFixedCount);
    $('#consultRoomsCount').text(data.consultRoomsCount);
    $('#consumablesCount').text(data.consumablesCount);
    // usersByRole
    $('#AllUsers').text(data.usersByRole.administrateur + data.usersByRole.receptionniste + data.usersByRole.medecins);
    $('#usersAdmin').text(data.usersByRole.administrateur);
    $('#usersReceptionist').text(data.usersByRole.receptionniste);
    $('#usersDoctor').text(data.usersByRole.medecins);
  });
}

function loadNonPeriodicDetails() {
    $.getJSON('/api/dashboard/nonperiodic/employees-distribution', function(data) {
        const listContainer = $('#employeeList'); // Ensure you have a container with this ID in your HTML
        listContainer.empty(); // Clear existing list items

        // Define badge classes for different roles
        const badgeClasses = ['badge-primary', 'badge-info', 'badge-secondary', 'badge-success', 'badge-warning'];

        // Iterate over the data and create list items
        Object.keys(data).forEach((key, index) => {
            // Capitalize the first letter of the role for display
            const displayName = key.charAt(0).toUpperCase() + key.slice(1);

            // Create a list item for each role
            const listItem = $(`
                <li class="list-group-item d-flex justify-content-between">
                    ${displayName}
                    <span class="badge ${badgeClasses[index % badgeClasses.length]} badge-pill">${data[key]}</span>
                </li>
            `);
            listContainer.append(listItem);
        });
    });
}

function loadLowStockConsumables() {
  $.getJSON('/api/dashboard/nonperiodic/low-stock-consumables', items => {
    const $ul = $('#lowStockList').empty();
    items.forEach(i => {
      $ul.append(
        `<li class="list-group-item d-flex justify-content-between">
           ${i.item}
           <span class="badge badge-danger badge-pill">${i.remaining} restants</span>
         </li>`
      );
    });
  });
}

function loadGlobalPatientsDetails() {
  $.getJSON('/api/dashboard/global/patients', s => { 
    $('#totalPatientsValue').text(s.total); 
    $('#genderRatioValue').text(`${s.female} / ${s.male}`); 
    $('#ageGroupsValue').text(`${s.minors} / ${s.adults} / ${s.seniors}`); 
    $('#averageAgeValue').text(`${Math.round(s.averageAge)} ans`);
  }).fail((jqXHR, textStatus) => {
    console.error('Erreur lors du chargement des détails global patients :', textStatus);
  });
}

function loadPeriodicPatients(from, to) {
  $.getJSON('/api/dashboard/periodic/patients', { from, to })
    .done(data => {
      // Nouveaux patients
      $('#periodicNewPatientsValue').text(data.newPatients ?? '--');
      // Patients de retour
      $('#periodicReturningPatientsValue').text(data.returningPatients ?? '--');
    })
    .fail((jqXHR, textStatus, errorThrown) => {
      console.error('Erreur chargement patients périodiques :', textStatus, errorThrown);
      // Optionnel : afficher un message d’erreur dans l’UI
      $('#periodicNewPatientsValue').text('Erreur');
      $('#periodicReturningPatientsValue').text('Erreur');
    });
}



function loadPeriodicConsultations(from, to) {
  $.getJSON('/api/dashboard/periodic/consultations', { from, to }, s => {
    $('#periodicTotalConsultsValue').text(s.total);
    $('#periodicPaidConsultsValue').text(s.paid);
    $('#periodicFreeConsultsValue').text(s.free);
    $('#periodicTotalAmountValue').text(formatFcfa(s.totalAmount));
    $('#periodicAvgAmountValue').text(formatFcfa(s.averageAmount));
    $('#periodicTopActsValue').text(s.topActs.join(', '));
  });
}

function loadPeriodicAppointments(from, to) {
  $.getJSON('/api/dashboard/periodic/appointments', { from, to }, s => {
    $('#periodicScheduled').text(s.scheduled);
    $('#periodicConfirmed').text(s.confirmed);
    $('#periodicPending').text(s.pending);
    $('#periodicPostponed').text(s.postponed);
    $('#periodicCancelled').text(s.cancelled);
    $('#periodicConfirmRate').text(s.confirmationRate + ' %');
    $('#periodicAvgDelay').text(s.averageDelayDays + ' jours');
  });
}

function loadPeriodicRoomUsage(from, to) {
  $.getJSON('/api/dashboard/periodic/room-usage', { from, to }, data => {
    const $ul = $('#roomUsageList').empty();

    // Check if data.usage exists and is an array
    if (data.usage && Array.isArray(data.usage)) {
      data.usage
        .filter(r => r.room) // Filter out entries without a room
        .forEach(r => {
          $ul.append(
            `<li class="list-group-item d-flex justify-content-between">
              ${r.room}
              <span class="badge badge-info badge-pill">${r.count} (${r.percent}%)</span>
            </li>`
          );
        });
    }

    // Set the top room text
    $('#topRoom').text(data.topRoom || 'No data available');
  });
}


function loadPeriodicPaymentBalances(from, to) {
  $.getJSON('/api/dashboard/periodic/payment-balances', { from, to })
    .done(modes => {
      const $ul = $('#paymentBalancesList').empty();
      modes.forEach(m => {
        $ul.append(
          `<li class="list-group-item d-flex justify-content-between">
             ${m.mode}
             <span class="badge badge-success badge-pill">${formatFcfa(m.balance)}</span>
           </li>`
        );
      });
    })
    .fail((jqXHR, textStatus) => {
      console.error('Erreur chargement soldes de paiement :', textStatus);
      $('#paymentBalancesList').empty().append(
        `<li class="list-group-item text-danger">Impossible de charger les soldes</li>`
      );
    });
}


function loadPeriodicPaymentFrequency(from, to) {
  $.getJSON('/api/dashboard/periodic/payment-frequency', { from, to })
    .done(data => {
      const $ul = $('#paymentFreqList').empty();
      // On parcourt data.frequency, pas directement data
      (data.frequency || []).forEach(m => {
        $ul.append(
          `<li class="list-group-item d-flex justify-content-between">
             ${m.mode}
             <span class="badge badge-secondary badge-pill">
               ${m.count} (${m.percent}%)
             </span>
           </li>`
        );
      });
      // On affiche le mode le plus utilisé
      $('#topPaymentMode').text(data.topMode || '—');
    })
    .fail((jqXHR, textStatus) => {
      console.error('Erreur chargement fréquence de paiement :', textStatus);
      $('#paymentFreqList').empty().append(
        `<li class="list-group-item text-danger">
           Impossible de charger les données
         </li>`
      );
      $('#topPaymentMode').text('—');
    });
}

function loadActsStats(from, to) {
  $.getJSON('/api/dashboard/periodic/acts-stats', { from, to }, acts => {
    const $ul = $('#actsStatsList').empty();
    Object.entries(acts).forEach(([type, count]) => {
      $ul.append(
        `<li class="list-group-item d-flex justify-content-between">
           ${type}
           <span class="badge badge-primary badge-pill">${count}</span>
         </li>`
      );
    });
  });
}

function loadDoctorReports(from, to) {
  $.getJSON('/api/dashboard/periodic/doctor-reports', { from, to }, resp => {
    // Affichage des KPI
    $('#kpiTotalRevenue').text(formatFcfa(resp.kpi.totalRevenue));
    $('#kpiAfterFees').text(formatFcfa(resp.kpi.afterFees));
    $('#kpiTotalSalaries').text(formatFcfa(resp.kpi.totalSalaries));

    // Réinitialisation propre de la table
    if ($.fn.DataTable.isDataTable('#doctorsReport')) {
      const dt = $('#doctorsReport').DataTable();
      dt.rows().every(function () {
        if (this.child.isShown()) {
          this.child.hide();
          $(this.node()).removeClass('shown');
        }
      });
      dt.destroy();
    }

    // Création du tableau
    $('#doctorsReport').DataTable({
      data: resp.doctors,
      columns: [
        { data: 'name' },
        { data: 'consultations' },
        {
          data: 'revenue',
          render: data => formatFcfa(data)
        },
        { data: 'paidVsFree' },
        {
          data: 'salary',
          render: data => formatFcfa(data)
        },
        {
          data: null,
          orderable: false,
          render: function (data, type, row) {
            return `
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-primary" onclick="printDoctorRow(this)">
                  <i class="fas fa-print"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary btn-toggle-details">
                  <i class="fas fa-chevron-down"></i>
                </button>
              </div>
            `;
          }
        } 
      ]
    });
  });
}

$('#doctorsReport tbody').on('click', '.btn-toggle-details', function () {
  const table = $('#doctorsReport').DataTable();
  const tr = $(this).closest('tr');
  const row = table.row(tr);

  // Toujours récupérer les données (même si vides)
  const data = row.data();
  if (!data) return;

  // Générer le HTML du détail (vide ou non)
  const detailHtml = formatDoctorDetails(data);

  if (row.child.isShown()) {
    row.child.hide();
    tr.removeClass('shown');
    $(this).html('<i class="fas fa-chevron-down"></i>');
  } else {
    row.child(detailHtml).show();
    tr.addClass('shown');
    $(this).html('<i class="fas fa-chevron-up"></i>');
  }
});



function formatDoctorDetails(d) {
  if (!d || !Array.isArray(d.actes) || d.actes.length === 0) {
    return '<div class="p-2"><em>Aucun acte enregistré sur cette période.</em></div>';
  }

  let table = `
    <div class="p-3">
      <table class="table table-sm table-bordered mb-0">
        <thead class="thead-light">
          <tr>
            <th>Date</th>
            <th>Patient</th>
            <th>Type d'acte</th>
            <th>Montant</th>
          </tr>
        </thead>
        <tbody>`;

  d.actes.forEach(a => {
    table += `
      <tr>
        <td>${a.date}</td>
        <td>${a.patient}</td>
        <td>${a.type}</td>
        <td>${formatFcfa(a.montant)}</td>
      </tr>`;
  });

  table += `</tbody></table></div>`;
  return table;
}



function printDoctorRow(btn) {
  const tr = $(btn).closest('tr');
  const row = $('#doctorsReport').DataTable().row(tr).data();

  let html = `
    <div style="font-family: Arial; font-size: 14px;">
      <h2 style="text-align: center;">Rapport de service — Dr ${row.name}</h2>
      
      <p><strong>Période :</strong> ${$('#reportrange').text() || '(non spécifiée)'}</p>
      <hr>
      ${formatDoctorDetails(row)}
      <br><br>
      <table style="width: 100%; margin-top: 50px;">
        <tr>
          <td style="text-align: left;"><strong>Signature Médecin</strong></td>
          <td style="text-align: right;"><strong>Signature Direction</strong></td>
        </tr>
      </table>
    </div>
  `;

  printJS({
    printable: html,
    type: 'raw-html',
    style: '@page { size: A4 landscape; margin: 20mm; }',
    scanStyles: false
  });
}




function printAllActs() {
  $('#globalPrintModal').modal('hide');

  const rows = $('#doctorsReport').DataTable().rows().data().toArray();
  let allActes = [];

  rows.forEach(r => {
    if (Array.isArray(r.actes)) {
      r.actes.forEach(a => {
        allActes.push({
          date: a.date,
          medecin: r.name,
          patient: a.patient,
          type: a.type,
          montant: a.montant
        });
      });
    }
  });

  let html = `
    <div style="font-family: Arial;">
      <h2 style="text-align: center;">Liste des actes médicaux</h2>
      <p><strong>Période :</strong> ${$('#reportrange').text() || '(non spécifiée)'}</p>
      <table style="width: 100%; border-collapse: collapse;" border="1" cellpadding="5">
        <thead>
          <tr>
            <th>Date</th>
            <th>Médecin</th>
            <th>Patient</th>
            <th>Type d'acte</th>
            <th>Montant</th>
          </tr>
        </thead>
        <tbody>`;

  allActes.forEach(a => {
    html += `
      <tr>
        <td>${a.date}</td>
        <td>${a.medecin}</td>
        <td>${a.patient}</td>
        <td>${a.type}</td>
        <td>${formatFcfa(a.montant)}</td>
      </tr>`;
  });

  html += `
        </tbody>
      </table>
      <br><br>
      <table style="width: 100%; margin-top: 50px;">
        <tr>
          <td style="text-align: left;"><strong>Signature Responsable</strong></td>
          <td style="text-align: right;"><strong>Cachet Cabinet</strong></td>
        </tr>
      </table>
    </div>
  `;

  printJS({
    printable: html,
    type: 'raw-html',
    style: '@page { size: A4 landscape; margin: 20mm; }',
    scanStyles: false
  });
}


// utilitaire de formatage en Fcfa
function formatFcfa(amount) {
  return new Intl.NumberFormat('fr-FR').format(amount) + ' Fcfa';
}
