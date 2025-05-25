$(document).ready(function () {
    console.log("Consultations fermées");
    $('#closedConsultationsTable').DataTable({
        ajax: {
            url: '/api/consultations/closed',
            dataSrc: ''
        },
        columns: [
            {data : 'id'},
            { data: 'date' },
            { data: 'patient' },
            { data: 'medecin' }, 
            { data: 'salle' },
            {
                data: 'id',
                render: function(data) {
                    return `
                    <button class="btn btn-sm btn-info btn-view-consultation"
                        data-id="${data}">
                        <i class="fas fa-eye me-1"></i> Voir
                    </button>
                    `;
                },
                orderable: false,
                searchable: false
            }
        ],
        language: {
            "sEmptyTable": "Aucune donnée disponible dans le tableau",
            "sInfo": "Affichage de l'élément _START_ à _END_ sur _TOTAL_ éléments",
            "sInfoEmpty": "Affichage de l'élément 0 à 0 sur 0 élément",
            "sInfoFiltered": "(filtré à partir de _MAX_ éléments au total)",
            "sLengthMenu": "Afficher _MENU_ éléments",
            "sLoadingRecords": "Chargement...",
            "sProcessing": "Traitement...",
            "sSearch": "Rechercher :",
            "sZeroRecords": "Aucun élément correspondant trouvé",
            "oPaginate": {
                "sFirst": "Premier",
                "sLast": "Dernier",
                "sNext": "Suivant",
                "sPrevious": "Précédent"
            }
        },
        responsive: true // Enable responsiveness
    });

    $(document).on('click', '.btn-view-consultation', function() {
        const id = $(this).data('id');
        $.getJSON(`/admin/consultation/${id}/details.json`, function(data) {
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
        }).fail(function() {
          showToastModal({
            message: 'Impossible de charger les détails de la consultation.',
            type: 'error',
            duration: 3000
          });
        });
      });

      $(document).on('click', '.btn-closer', function() {
        $(this).closest('.modal').modal('hide'); 
      })
      
});