$(document).ready(function () { // Initialize DataTables for Consommables and Stock
    let devisStart = moment().startOf('month').format('YYYY-MM-DD');
    let devisEnd = moment().format('YYYY-MM-DD');

    const deleteTokensById = new Map();

    const consommablesTable = $('#consommablesTable').DataTable({
        ajax: {
            url: '/api/consumables',
            dataSrc: function (json) {
                deleteTokensById.clear();
                if (Array.isArray(json)) {
                    json.forEach(row => {
                        if (row && row.id && row.deleteToken) {
                            deleteTokensById.set(row.id, row.deleteToken);
                        }
                    });
                }
                return json;
            },
            error: function () {
                showToastModal({ message: 'Erreur lors de la récupération des consommables.', type: 'error', duration: 3000 });
            }
        },
        columns: [
            {
                data: 'nom'
            },
            {
                data: 'quantity'
            },
            {
                data: 'fournisseur'
            },
            {
                data: 'onlowvalue',
                render: function (data) {
                    return `<span class="badge text-white ${data ? 'bg-danger' : 'bg-success'}">
                                <i class="fas ${data ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i>
                            </span>`;
                }
            }, {
                data: 'id',
                render: function (data) {
                    return `
                        <button class="btn btn-sm btn-warning action-btn" onclick="handleRetraitClick(${data})">
                            <i class="fas fa-minus-circle"></i>
                        </button>
                        <button class="btn btn-sm btn-primary action-btn" onclick="handleAddStockClick(${data})">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <button class="btn btn-sm btn-info action-btn" onclick="handleModStockClick(${data})">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-danger action-btn" onclick="handleDeleteClick(${data})">
                            <i class="fas fa-trash-alt"></i>
                        </button>`;
                }
            }
        ],
        language: {
            url: '/js/utils/datatables_fr.json'
        } 
    });

    const stockTable = $('#stockTable').DataTable({
        ajax: {
            url: '/api/stocks',
            dataSrc: '',
            data: function (d) {
                d.start = devisStart;
                d.end = devisEnd;
            },
            error: function (xhr) {
                console.error('Stock API error:', xhr.responseText);
                stockTable.clear().draw();
                $('#stockTable tbody').html('<tr><td colspan="5" class="text-center">Erreur lors du chargement des données</td></tr>');
                showToastModal({ message: 'Erreur lors du chargement des données de stock.', type: 'error', duration: 3000 });
            }
        },
        columns: [
            {
                data: 'date',
                render: function (data) {
                    const dateObj = new Date(data);
                    return dateObj.toLocaleDateString() + ' ' + dateObj.toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            },

            {
                data: null,
                render: function (data) {
                    const isRetrait = data.type === 'Retrait';
                    const classe = isRetrait ? 'text-danger' : 'text-success';
                    const signe = isRetrait ? '-' : '+';
                    return `<span class="${classe}">${signe}${data.quantiteUtilisee}</span>`;
                }
            },
            {
                data: 'consommable'
            },
            {
                data: 'employe'
            }, {
                data: 'description'
            }
        ],
        language: {
            url: '/js/utils/datatables_fr.json'
        }
    });

    // Date change handler
    $('#startDate, #endDate').change(function () {
        stockTable.ajax.reload();
    });

    // Fetch and populate employees
    $.ajax({
        url: '/api/employees',
        method: 'GET',
        success: function (response) {
            const employees = response.data;
            if (!Array.isArray(employees)) {
                console.error('Invalid data format: Expected an array.');
                showToastModal({ message: 'Format de données invalide: tableau attendu.', type: 'error', duration: 3000 });
                return;
            }
            const employeeOptions = employees.map(employee => `<option value="${employee.id}">${employee.nom} ${employee.prenom}</option>`).join('');
            $('select[name="employe"]').html('<option value="" disabled selected>Choisir un employé</option>' + employeeOptions);
        },
        error: function () {
            showToastModal({ message: 'Erreur lors de la récupération des employés.', type: 'error', duration: 3000 });
        }
    });

    // Toggle sections
    const btnCons = $('#btn-consommables');
    const btnStock = $('#btn-stock');
    const sectionCons = $('#section-consommables');
    const sectionStock = $('#section-stock');

    btnCons.on('click', () => {
        sectionCons.removeClass('d-none');
        sectionStock.addClass('d-none');
        btnCons.removeClass('btn-outline-primary').addClass('btn-primary');
        btnStock.removeClass('btn-primary').addClass('btn-outline-secondary');
    });

    btnStock.on('click', () => {
        sectionStock.removeClass('d-none');
        sectionCons.addClass('d-none');
        btnStock.removeClass('btn-outline-secondary').addClass('btn-primary');
        btnCons.removeClass('btn-primary').addClass('btn-outline-primary');
    });

    // Open add consumable modal
    $('#btn-open-ajout-modal').on('click', () => {
        $('#ajoutConsommableModal').modal('show');
    });

    // Handle form submissions
    $('#ajoutConsommableModal form').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            url: '/api/consumables',
            method: 'POST',
            data: formData,
            success: function (response) {
                showToastModal({ message: response.message, type: 'success', duration: 3000 });
                $('#ajoutConsommableModal').modal('hide');
                consommablesTable.ajax.reload();
            },
            error: function (xhr) {
                showToastModal({
                    message: 'Erreur lors de l\'ajout du consommable: ' + xhr.responseText,
                    type: 'error',
                    duration: 3000
                });
            }
        });
    });

    $('#retraitModal form').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            url: `/api/consumables/${$('#retrait-consommable-id').val()}/withdraw`,
            method: 'POST',
            data: formData,
            success: function (response) {
                showToastModal({ message: response.message, type: 'success', duration: 3000 });
                $('#retraitModal').modal('hide');
                consommablesTable.ajax.reload();
            },
            error: function (xhr) {
                showToastModal({
                    message: 'Erreur lors du retrait du stock: ' + xhr.responseText,
                    type: 'error',
                    duration: 3000
                });
            }
        });
    });

    $('#addStockModal form').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            url: `/api/consumables/${$('#add-stock-consommable-id').val()}/stock`,
            method: 'POST',
            data: formData,
            success: function (response) {
                showToastModal({ message: response.message, type: 'success', duration: 3000 });
                $('#addStockModal').modal('hide');
                consommablesTable.ajax.reload();
            },
            error: function (xhr) {
                showToastModal({
                    message: 'Erreur lors de l\'ajout de stock: ' + xhr.responseText,
                    type: 'error',
                    duration: 3000
                });
            }
        });
    });

    // Handle clicks for modals
    let deleteId = null;
    let deleteToken = null;

    window.handleRetraitClick = function (id) {
        $('#retrait-consommable-id').val(id);
        $('#retraitModal').modal('show');
    };

    window.handleAddStockClick = function (id) {
        $('#add-stock-consommable-id').val(id);
        $('#addStockModal').modal('show');
    };

    window.handleModStockClick = function (id) { // Set the consumable ID in the modal data
        $('#modConsommableModal').data('id', id);

        // Fetch the consumable details using AJAX
        $.ajax({
            url: `/api/consumables/${id}`,
            method: 'GET',
            success: function (response) { // Populate the modal form fields with the consumable details
                $('#modConsommableModal input[name="nom"]').val(response.nom);
                $('#modConsommableModal input[name="fournisseur"]').val(response.fournisseur);
                $('#modConsommableModal input[name="lowValue"]').val(response.lowValue);

                // Show the modal
                $('#modConsommableModal').modal('show');
            },
            error: function (xhr) { // Show an error message if the request fails
                showToastModal({
                    message: 'Erreur lors de la récupération des détails du consommable: ' + xhr.responseText,
                    type: 'error',
                    duration: 3000
                });
            }
        });
    };

    $('#modConsommableModal form').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            url: `/api/consumables/${$('#modConsommableModal').data('id')}`,
            method: 'PUT',
            data: formData,
            success: function (response) {
                showToastModal({ message: "Modifications enregistrées avec succès.", type: 'success', duration: 3000 });
                $('#modConsommableModal').modal('hide');
                consommablesTable.ajax.reload();
            },
            error: function (xhr) {
                showToastModal({
                    message: 'Erreur lors de l\'ajout de stock: ' + xhr.responseText,
                    type: 'error',
                    duration: 3000
                });
            }
        });
    });


    window.handleDeleteClick = function (id) {
        deleteId = id;
        deleteToken = deleteTokensById.get(id) || null;
        $('#deleteModal').modal('show');
    };

    $('#confirm-delete-btn').on('click', function () {
        if (deleteId) {
            if (!deleteToken) {
                showToastModal({ message: 'Jeton CSRF manquant. Rechargez la page et réessayez.', type: 'error', duration: 4000 });
                return;
            }
            $.ajax({
                url: `/api/consumables/${deleteId}`,
                method: 'DELETE',
                data: {
                    _token: deleteToken
                },
                success: function (response) {
                    showToastModal({ message: response.message, type: 'success', duration: 3000 });
                    $('#deleteModal').modal('hide');
                    consommablesTable.ajax.reload();
                },
                error: function (xhr) {
                    showToastModal({
                        message: 'Erreur lors de la suppression: ' + xhr.responseText,
                        type: 'error',
                        duration: 3000
                    });
                }
            });
        }
    });

    // Initialize date range picker
    $('#daterange').daterangepicker({
        locale: {
            format: 'YYYY-MM-DD',
            applyLabel: 'Appliquer',
            cancelLabel: 'Annuler',
            daysOfWeek: [
                'Di',
                'Lu',
                'Ma',
                'Me',
                'Je',
                'Ve',
                'Sa'
            ],
            monthNames: [
                'Janvier',
                'Février',
                'Mars',
                'Avril',
                'Mai',
                'Juin',
                'Juillet',
                'Août',
                'Septembre',
                'Octobre',
                'Novembre',
                'Décembre'
            ],
            firstDay: 1
        },
        opens: 'left',
        alwaysShowCalendars: true,
        startDate: devisStart,
        endDate: devisEnd,
        ranges: {
            "Aujourd'hui": [
                moment(), moment()
            ],
            "Hier": [
                moment().subtract(1, 'days'),
                moment().subtract(1, 'days')
            ],
            "Cette semaine": [
                moment().startOf('week'), moment().endOf('week')
            ],
            "Ce mois-ci": [
                moment().startOf('month'), moment().endOf('month')
            ],
            "Cette année": [moment().startOf('year'), moment().endOf('year')]
        }
    }, function (start, end) {
        devisStart = start.format('YYYY-MM-DD');
        devisEnd = end.format('YYYY-MM-DD');
        stockTable.ajax.reload();
    });

    // Close modals
    $('.btn-close').on('click', function () {
        $(this).closest('.modal').modal('hide');
    });
});