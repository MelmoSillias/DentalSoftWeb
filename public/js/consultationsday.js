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
            { data: 'numero', title: 'N°' },
            { data: 'patient', title: 'Patient' },
            { data: 'medecin', title: 'Médecin' },
            { data: 'createdAt', title: 'Date Création' },
            {
                data: 'id',
                title: 'Actions',
                render: function (data, type, row) {
                    return row.state === 0
                        ? `<button class="btn btn-sm btn-danger cancel-btn" data-id="${row.id}">
                               <i class="fas fa-times"></i> Annuler
                           </button>`
                        : '';
                },
                orderable: false,
                searchable: false
            }
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
});
