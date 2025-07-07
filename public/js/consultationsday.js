$(function () {
    const table = $('#consultationTable').DataTable({
        ajax: {
            url: '/api/consultations/jour',
            dataSrc: ''
        },
        columns: [
            { data: 'numero', title: 'N°' },
            { data: 'patient', title: 'Patient' },
            { data: 'medecin', title: 'Médecin' },
            { data: 'createdAt', title: 'Date Création' },
            {
                data: 'id',
                title: 'Actions',
                render: function (data, type, row) {
                    if (row.state === 0) {
                        return `<button class="btn btn-sm btn-danger cancel-btn" data-id="${row.id}">
                                    <i class="fas fa-times"></i> Annuler
                                </button>`;
                    }
                    return ''; // pas de bouton si state != 0
                },
                orderable: false,
                searchable: false
            }
        ],
        rowCallback: function (row, data) {
            if (data.state === 1) {
                $(row).addClass('table-success'); // ligne verte
            }
        },
        language: {
            sEmptyTable: "Aucune donnée disponible",
            sInfo: "Affichage de _START_ à _END_ sur _TOTAL_ consultations",
            sLengthMenu: "Afficher _MENU_ consultations",
            sSearch: "Rechercher :",
            oPaginate: { sNext: "Suivant", sPrevious: "Précédent" }
        }
    });

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
      $('#consultationTable').DataTable().ajax.reload(); // recharge la table
    },
    error: function () {
    ShowToastModal({ message: "Erreur lors de l'annulation", type: "error" });
    }
  });
});

});
