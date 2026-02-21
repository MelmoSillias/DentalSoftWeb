$(document).ready(function () {
    const $modeTable = $('#modePaiementTable').DataTable({
        ajax: {
            url: '/api/payment-methods',
            dataSrc: ''
        },
        columns: [
            {
                data: 'libelle'
            },{
                data: 'actif',
                render: actif => actif ? '<span class="badge bg-success text-white">Actif</span>' : '<span class="badge bg-secondary text-white">Inactif</span>'
            }, {
                data: 'id',
                render: function (id, type, row) {
                    if (row.libelle.toLowerCase() === 'espèces') {
                        return `<span class="text-muted"><i class="fas fa-lock me-1"></i>Verrouillé</span>`;
                    }

                    return `
                            <button class="btn btn-sm btn-outline-${row.actif ? 'warning' : 'success'} toggle-btn" data-id="${id}">
                                <i class="fas fa-toggle-${row.actif ? 'off' : 'on'}"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${id}">
                                <i class="fas fa-trash-alt"></i>
                            </button>`;
                },
                orderable: false,
                searchable: false
            }
        ],
        language: {
            url: '/js/utils/datatables_fr.json'
        }
    });

    $('[data-bs-toggle="modal"]').on('click', function () {
        $('#modalAddMode').modal('show');
    });

    function closeAddModal() {
        $('#formAddMode')[0].reset();
        $('#modalAddMode').modal('hide');
    }

    $('#formAddMode').on('submit', function (e) {
        e.preventDefault();
        const data = {
            libelle: $('#libelleMode').val(),
            type: $('#typeMode').val(),
            notes: $('#notesMode').val()
        };

        $.ajax({
            url: '/api/payment-methods',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function () {
                closeAddModal();
                $modeTable.ajax.reload();
            },
            error: function () {
                alert('Erreur lors de l’enregistrement.');
            }
        });
    });

    $('#modePaiementTable').on('click', '.delete-btn', function () {
        const id = $(this).data('id');
        if (confirm('Confirmer la suppression ?')) {
            $.ajax({
                url: `/api/payment-methods/${id}`,
                method: 'DELETE',
                success: function () {
                    $modeTable.ajax.reload();
                },
                error: function () {
                    alert('Erreur lors de la suppression.');
                }
            });
        }
    });

    $('#modePaiementTable').on('click', '.toggle-btn', function () {
        const id = $(this).data('id');
        $.ajax({
            url: `/api/payment-methods/${id}/toggle`,
            method: 'PATCH',
            success: function () {
                $modeTable.ajax.reload();
            },
            error: function () {
                alert('Erreur lors de l’activation/désactivation.');
            }
        });
    });

    function loadComptes() {
        $.get('/api/payment-methods', function (data) {
            const $select = $('#mode');
            $select.empty();
            data.forEach(mode => {
                if (mode.actif) {
                    $select.append(`<option value="${mode.id}">${mode.libelle}</option>`);
                }
            });
        });
    }

    loadComptes();

    $('#formInterCompte').on('submit', function (e) {
        e.preventDefault();
        const data = {
            from: $('#fromAccount').val(),
            to: $('#toAccount').val(),
            montant: $('#amountTransfert').val(),
            motif: $('#motifTransfert').val()
        };

        $.ajax({
            url: '/api/transactions/intercompte',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function () {
                $('#interCompteModal').modal('hide');
                $('#transactionsTable').DataTable().ajax.reload();
            },
            error: function () {
                alert("Échec du transfert");
            }
        });
    });
});