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
                        <a href="/admin/consultation/${data}/details" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> Détails
                        </a>
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
});