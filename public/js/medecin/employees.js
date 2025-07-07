$(document).ready(function () {
    $('#mainFab').on('click', function () {
        $('#addEmployeeModal').modal('show');
    });

    const employeeTable = $('#employeeTable').DataTable({
        ajax: {
            url: '/api/employees',
            type: 'GET',
            data: function (d) {
                d.typeFilter = $('input[name="filterType"]:checked').val();
            },
            error: function (xhr, error, thrown) {
                console.error('DataTables AJAX error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error,
                    thrown: thrown
                });
                showToastModal({
                  message: 'Erreur lors du chargement des données. Voir la console.',
                  type: 'error',
                  duration: 3000
                });
            }
        },
        processing: true,
        serverSide: true,
        columns: [
            { data: 'nom' },
            { data: 'prenom' },
            { data: 'type' },
            { data: 'telephone' },
            { data: 'dateEmbauche' },
            {
                data: null,
                orderable: false,
                render: function (data) {
                    return ` 
                        <a href="/admin/employee/details/${data.id}" class="btn btn-sm btn-info">
                            <i class="fas fa-info-circle"></i>
                        </a>`;
                }
            }
        ],
        order: [[0, 'asc']],
        rowGroup: {
            dataSrc: 'type'
        },
        language: {
            emptyTable: "Aucune donnée disponible",
            info: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
            lengthMenu: "Afficher _MENU_ éléments",
            search: "Rechercher :",
            paginate: {
                next: "Suivant",
                previous: "Précédent"
            }
        }
    });

    $('<button id="toggleGrouping" class="btn btn-secondary btn-sm ms-2">Désactiver le regroupement</button>')
        .appendTo('#employeeTable_filter')
        .on('click', function () {
            const grp = employeeTable.rowGroup();
            const enabled = !grp.enabled();
            grp.enable(enabled).draw();
            $(this).text(enabled ? 'Désactiver le regroupement' : 'Activer le regroupement');
        });

    $('input[name="filterType"]').on('change', function () {
        employeeTable.ajax.reload();
    });

    // ===== FORMULAIRE DE MISE À JOUR =====
    $('#employeeForm').on('submit', function (e) {
        e.preventDefault();

        // Synchroniser champ caché 'type'
        $('#typeHidden').val($('#typeSelect').val());

        const formData = new FormData();

        // Ajouter les champs texte (sauf comingDays[])
        $(this).serializeArray().forEach(({ name, value }) => {
            if (!name.startsWith('comingDays')) {
                formData.append(name, value);
            }
        });

        // Ajouter les jours sélectionnés
        $('input[name="comingDays[]"]:checked').each(function () {
            formData.append('comingDays[]', $(this).val());
        });

        // Ajouter les fichiers FilePond manuellement
        pondInstance.getFiles().forEach(fileItem => {
            formData.append('administrativeFiles[]', fileItem.file);
        });
 
        $.ajax({
            url: '/api/employee/new',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function () {
                $('#addEmployeeModal').modal('hide');
                $('#employeeForm')[0].reset();
                employeeTable.ajax.reload(null, false);
                showToastModal({
                  message: 'Employé créé avec succès.',
                  type: 'success'
                });
            },
            error: function (xhr) {
                showToastModal({
                  message: 'Erreur : ' + xhr.responseText,
                  type: 'error',
                  duration: 3000
                });
            }
        });
    });

    $('#employeeType, #editType').on('change', function () {
        const isCommission = $(this).val() === 'Autre';
        const usernameField = this.id === 'employeeType' ? '#usernameField' : '#editUsernameField';
        const pourcentageField = this.id === 'employeeType' ? '#pourcentageField' : '#editPourcentageField';
        toggleFields($(this).val(), usernameField, pourcentageField);
    });

    function toggleFields(typeValue, usernameSelector, pourcentageSelector) {
        if (typeValue === 'Autre') {
            $(usernameSelector).show();
            $(pourcentageSelector).show();
        } else {
            $(usernameSelector).hide();
            $(pourcentageSelector).hide();
        }
    }
});
