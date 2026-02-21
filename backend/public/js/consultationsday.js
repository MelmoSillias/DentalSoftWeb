$(function () {
    const $datepickerInput = $('#datepicker input');
    const $consultationTable = $('#consultationTable');
    const $headerTitle = $('h1');
    let currentDate = formatDateToApi(new Date());

    // Fonction utilitaire pour formater une date en yyyy-mm-dd
    function formatDateToApi(date) {
        const d = new Date(date);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    // Fonction utilitaire pour formater une date en dd/mm/yyyy pour affichage
    function formatDateToDisplay(date) {
        const d = new Date(date);
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    }



    // Initialisation à la date du jour
    const now = new Date();
    $datepickerInput.datepicker('setDate', now);
    const todayApiFormat = formatDateToApi(now);
    const todayDisplay = formatDateToDisplay(now);
    $headerTitle.text('Consultations du ' + todayDisplay);

    // Initialisation de la DataTable
    const table = $consultationTable.DataTable({
        ajax: {
            url: `/api/consultations/jour`,
            data: function (d) {
                d.date = currentDate
            },
            dataSrc: 'data'
        },
        columns: [ 
            { data: 'patient', title: 'Patient' },
            { data: 'medecin', title: 'Médecin' },
            { data: 'createdAt', title: 'Date Création' },
            {
                data: 'id',
                title: 'Actions',
                render: function (data, type, row) {  
                     let html = `
                     ${row.state === 0 ? `<button class="btn btn-sm btn-danger cancel-btn" data-id="${row.id}"> <i class="fas fa-times"></i> </button>`  : ''} 
                      ${ row.state === 0 ? ``:  `<a class="btn btn-sm btn-view-dossier btn-info"
                        href="#" data-patient-id="${row.patientId}">
                        <i class="fas fa-folder-open me-1"></i> 
                      </a> 
                      <a class="btn btn-sm btn-modify-facture btn-secondary ${row.factstate === 0 ? '' : 'd-none'}"
                        href="#" data-consult-id="${row.id}">
                        <i class="fas fa-edit me-1"></i>
                      </a>`}
                `;
 

                return html; 
                },
            },
        ],
        rowCallback: function (row, data) {
            if (data.state === 1) {
                $(row).addClass('table-success');
            }
        },
        language: {
            sEmptyTable: "Aucune donnée disponible",
            sInfo: "Affichage de _START_ à _END_ sur _TOTAL_ consultations",
            sLengthMenu: "Afficher _MENU_ consultations",
            sSearch: "Rechercher :",
            oPaginate: { sNext: "Suivant", sPrevious: "Précédent" }
        }
    });



    // Gestion annulation consultation
    let selectedConsultationId = null;

    $(document).on('click', '.cancel-btn', function () {
        selectedConsultationId = $(this).data('id');
        $('#confirmCancelModal').modal('show');
    });

    $('#confirmCancelBtn').on('click', function () {
        if (!selectedConsultationId) return;

        $.ajax({
            url: `/api/consultation/${selectedConsultationId}`,
            type: 'DELETE',
            success: function () {
                $('#confirmCancelModal').modal('hide');
                $consultationTable.DataTable().ajax.reload();
            },
            error: function () {
                ShowToastModal({ message: "Erreur lors de l'annulation", type: "error" });
            }
        });
    });

    // Initialisation du datepicker
    $datepickerInput.datepicker({
        format: 'dd/mm/yyyy',
        todayHighlight: true,
        autoclose: true
    }).on('changeDate', function (e) {
        const selectedDate = e.date;
        const apiDate = formatDateToApi(selectedDate);
        currentDate = apiDate;
        const displayDate = formatDateToDisplay(selectedDate);

        $headerTitle.text('Consultations du ' + displayDate);
        if ($consultationTable.DataTable().ajax.url()) { $consultationTable.DataTable().ajax.reload() };

    });

    // 1. Voir le dossier patient
    $(document).on('click', '.btn-view-dossier', function () {
        const patientId = $(this).data('patient-id');
        // Adaptez l’URL selon votre route Symfony
        window.location.href = `/admin/patient/${patientId}/dossier`;
    });

    $(document).on('click', '.btn-view-consultation', function () {
        const id = $(this).data('id');
        $.getJSON(`/admin/consultation/${id}/details.json`, function (data) {
            $('#detail-id').text(data.id);
            $('#detail-date').text(data.date);
            $('#detail-patient').text(data.patient);
            $('#detail-medecin').text(data.medecin || '—');
            $('#detail-infirmier').text(data.infirmier || '—');
            $('#detail-salle').text(data.salle || '—');
            $('#detail-note').text(data.noteSeance || '—');

            // — Actes médicaux
            const $tbody = $('#detail-actes').empty();
            data.actes.forEach(a => {
                const total = (a.quantite * a.prix).toFixed(2);
                $tbody.append(`
        <tr>
        <td>${a.dent}</td>
        <td>${a.type}</td>
        <td>${a.description}</td>
        <td class="text-end">${a.quantite}</td>
        <td class="text-end">${a.prix.toFixed(2)}</td>
        <td class="text-end">${total}</td>
        </tr>
    `);
            });

            // Affiche le modal
            const modal = new bootstrap.Modal(document.getElementById('consultationDetailsModal'));
            modal.show();
        }).fail(function () {
            showToastModal({
                message: 'Impossible de charger les détails de la consultation.',
                type: 'error',
                duration: 3000
            });
        });
    });

    $(document).on('click', '.btn-closer', function () {
        $(this).closest('.modal').modal('hide');
    })

    // 2. Modifier la facture
    $(document).on('click', '.btn-modify-facture', function () {
        const consultId = $(this).data('consult-id');
        openModifyFactureModal(consultId);
    });


    function openModifyFactureModal(consultId) {
        $('#factureLinesContainer').empty();
        $('#factureTotal').text('0.00');
        $('#btnSaveFacture').data('id', consultId);
        $('#modifyFactureModal').modal('show');

        // Récupérer les lignes de la facture via AJAX
        $.get(`/api/consultation/${consultId}/facture`, function (lines) {
            // lines = [ { dent, type, prix, quantite, description, idLigne }, ... ]
            lines.forEach(l => {
                const blk = createFactureLineBlock(l);
                $('#factureLinesContainer').append(blk);
            });
            recalcFactureTotal();

        });
    }

    function uniqueId(prefix = 'id') {
        return `${prefix}_${Date.now()}_${Math.floor(Math.random() * 1000)}`;
    }

    // Fonction de création d’un bloc « ligne de facture » (adaptée de createActeBlock)
    function createFactureLineBlock(data = {}) {
        const uid = uniqueId('ligne'); // même fonction uniqueId
        const $blk = $(`
    <div class="ligne-facture mb-3 border p-2" id="${uid}">
      <div class="row gx-2"> 
        <div class="col-md-4">
          <label>Description</label>
          <textarea class="form-control ligne-desc" rows="2">${data.designation || ''}</textarea>
        </div>
        <div class="col-md-2">
          <label>Prix (€)</label>
          <input type="number" step="0.01" class="form-control ligne-prix" value="${data.montant || 0}">
        </div>
        <div class="col-md-2">
          <label>Quantité</label>
          <input type="number" class="form-control ligne-qte" value="${data.quantite || 1}">
        </div>
        
        <div class="col-md-1 d-flex align-items-end">
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>
    </div>
  `);

        // Recalcule automatique du total à chaque modification
        $blk.on('input', '.ligne-prix, .ligne-qte', recalcFactureTotal);
        // Suppression de la ligne
        $blk.on('click', '.btn-remove-ligne', function () {
            $blk.remove();
            recalcFactureTotal();
        });
        return $blk;
    }

    // Ajout d’une nouvelle ligne vide
    $('#btnAddLigne').on('click', function () {
        $('#factureLinesContainer').append(createFactureLineBlock());
        recalcFactureTotal();
    });

    // Recalcul du total TTC
    function recalcFactureTotal() {
        let total = 0;
        $('#factureLinesContainer .ligne-facture').each(function () {
            const prix = parseFloat($(this).find('.ligne-prix').val()) || 0;
            const qte = parseInt($(this).find('.ligne-qte').val()) || 0;
            total += prix * qte;
        });
        $('#factureTotal').text(total.toFixed(2));
    }

    // Enregistrement de la facture modifiée
    $('#btnSaveFacture').on('click', function () {
        const payload = [];
        const consultId = $(this).data('id')
        $('#factureLinesContainer .ligne-facture').each(function () {
            payload.push({
                prix: parseFloat($(this).find('.ligne-prix').val()),
                quantite: parseInt($(this).find('.ligne-qte').val()),
                description: $(this).find('.ligne-desc').val()
                // ajoutez éventuellement l’ID de la ligne si vous en avez besoin
            });
        });

        $.ajax({
            url: `/api/consultation/${consultId}/facture/update`, // stockez-le dans une variable globale
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ lignes: payload }),
            success: function () {
                $('#modifyFactureModal').modal('hide');
                // rafraîchir le DataTable
                $('#closedConsultationsTable').DataTable().ajax.reload(null, false);
            },
            error: function (err) {
                console.error(err);
                alert('Erreur lors de l’enregistrement de la facture');
            }
        });
    }); 
});
