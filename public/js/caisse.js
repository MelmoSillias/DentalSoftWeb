$(function () { // Variables globales pour les filtres de date
    let devisStart = moment().format('YYYY-MM-DD');
    let devisEnd = moment().format('YYYY-MM-DD');
    let AdevisStart = moment().startOf('month').format('YYYY-MM-DD');
    let AdevisEnd = moment().endOf('month').format('YYYY-MM-DD');
    let devisTable;
    const devisUrl = $('#devisTableTypeSelect option:selected').val() == "all" ? '/api/devis/all' : '/api/devis/impayes'

    // === 1. Rendu des Devis Impayés ===
    devisTable = $('#devisImpayesTable').DataTable({
        ajax: {
            url: devisUrl,
            method: 'GET',
            data: function (d) {
                d.start = AdevisStart;
                d.end = AdevisEnd;
            },
            dataSrc: function (json) { // Calcul des stats dans le callback
                const totalReste = json.reduce((sum, d) => sum + (parseFloat(d.reste) || 0), 0);
                $('#devisCount').text(json.length);
                $('#totalRestant').text(`${totalReste.toLocaleString('fr-FR')
                    } FCFA`);

                if (json.length === 0) {
                    $('#devisEmptyMessage').removeClass('d-none');
                } else {
                    $('#devisEmptyMessage').addClass('d-none');
                }

                return json;
            }
        },
        columns: [
            {
                data: 'date',
                render: date => new Date(date).toLocaleDateString('fr-FR')
            },
            {
                data: 'patient',
                render: p => `${p.nom
                    } ${p.prenom
                    }`
            },
            {
                data: 'telephone'
            },
            {
                data: 'montant',
                render: m => `${parseFloat(m).toLocaleString('fr-FR')
                    } FCFA`
            }, {
                data: 'reste',
                render: r => `${parseFloat(r).toLocaleString('fr-FR')
                    } FCFA`
            }, {
                data: null,
                orderable: false,
                searchable: false,
                render: row => {
                    const montant = parseFloat(row.montant);
                    const reste = parseFloat(row.reste);
                    let statut = '';
                    let badgeClass = '';

                    if (row.isRegle && reste === 0) {
                        statut = 'Payé';
                        badgeClass = 'badge-success';
                    } else if (!row.isRegle && reste === 0) {
                        statut = 'vide non validé';
                        badgeClass = 'badge-secondary';
                    } else if (reste === montant) {
                        statut = 'Impayé';
                        badgeClass = 'badge-danger';
                    } else {
                        statut = 'Partiellement payé';
                        badgeClass = 'badge-warning';
                    }

                    return `<span class="badge ${badgeClass}">${statut}</span>`;
                }
            }, {
                data: 'id',
                orderable: false,
                searchable: false,
                render: (id, type, row) => {
                    const statut = row.statut;
                    const isRegle = statut == 1;
                    const modifiable = row.montant === row.reste 
                    const reste = row.reste
                    const reglerButton = isRegle ? '' : `<a href="#" class="btn btn-sm btn-success regler-devis-btn" data-toggle="modal"
                            data-target="${!row.isRegle && reste === 0 ?  "#validateFreeDevisModal" : "#reglerDevisModal"}" data-devis-id="${id}">
                            <i class="fas fa-hand-holding-usd"></i>
                        </a>`;
                    const modButton = modifiable ?  `<a href="#" class="btn btn-sm btn-secondary mod-devis-btn" data-toggle="modal"
                            data-target="#modifyFactureModal" data-devis-id="${id}" data-consult-id="${row.consultation}">
                            <i class="fas fa-pencil-alt"></i>
                        </a>`:'' ;

                    return `
        <div>${reglerButton} ${modButton}
          <a href="#" class="btn btn-sm btn-primary preview-devis-btn ${row.montant === 0 && reste === 0 ?  "d-none" : ""}" data-toggle="modal"
             data-target="#devisModal" data-devis-id="${id}">
             <i class="fas fa-eye"></i>
          </a>
          
        </div>
      `;
                }
            }
        ],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/fr-FR.json'
        },
        order: [
            [0, 'desc']
        ]
    });

    $(document).on('click', '.mod-devis-btn', function () {
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
                devisTable.ajax.reload(null, false);
            },
            error: function (err) {
                console.error(err);
                alert('Erreur lors de l’enregistrement de la facture');
            }
        });
    }); 


    // === 2. Paiements : Date Range Picker ===
    $('#devisDateRange').daterangepicker({
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
        paiementsTable.ajax.reload();
    });

    $('#AlldevisDateRange').daterangepicker({
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
        startDate: AdevisStart,
        endDate: AdevisEnd,
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
        AdevisStart = start.format('YYYY-MM-DD');
        AdevisEnd = end.format('YYYY-MM-DD');
        devisTable.ajax.reload();
    });

    // === 3. DataTable des Paiements ===
    const paiementsTable = $('#paiementsDevisTable').DataTable({
        ajax: {
            url: '/api/paiements-devis',
            data: function (d) {
                d.start = devisStart;
                d.end = devisEnd;
            }
        },
        columns: [
            {
                data: 'date',
                render: d => {
                    const date = new Date(d);
                    return date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            },
            {
                data: 'patient'
            },
            {
                data: 'telephone'
            },
            {
                data: 'montant',
                render: m => `${parseFloat(m).toLocaleString('fr-FR')
                    } FCFA`
            }, {
                data: 'mode'
            }, {
                data: 'pId',
                orderable: false,
                searchable: false,
                render: (id, type, row) => {
                    const isDevis = row.type === 'devis';
                    const btnClass = isDevis ? 'btn-outline-secondary' : 'btn-info';
                    const iconClass = isDevis ? 'fa-print' : 'fa-ticket-alt';
                    const printClass = isDevis ? 'imprimer-paiement-btn' : 'imprimer-ticket-btn';
                    return `
              <a href="#" class="btn btn-sm ${btnClass} ${printClass}" data-id="${id}">
                <i class="fas ${iconClass}"></i>
              </a>
            `;
                }
            }
        ],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/fr-FR.json'
        },
        order: [
            [0, 'asc']
        ]
    });

    // === 4. Total recettes dynamiques ===
    paiementsTable.on('draw', function () {
        const data = paiementsTable.column(3, { search: 'applied' }).data().toArray();
        const total = data.reduce((sum, val) => sum + (parseFloat(val) || 0), 0);
        $('#recetteTotalPeriode').text(`${total.toLocaleString('fr-FR')
            } FCFA`);
    });

    // Paiement unique
    $('#paiementsDevisTable').on('click', '.imprimer-paiement-btn', function (e) {
        e.preventDefault();
        const paiementId = $(this).data('id');
        window.open(`/api/paiement-devis/${paiementId}/print`, '_blank', 'width=800,height=900,scrollbars=no');
    });

    $('#paiementsDevisTable').on('click', '.imprimer-ticket-btn', function (e) {
        e.preventDefault();
        const paiementId = $(this).data('id');
        const url = `/api/paiement-ticket/${paiementId}/print`;
        window.open(url, '_blank', 'width=800,height=900,scrollbars=no');
    });

    $('#imprimerPaiementsBtn').on('click', function (e) {
        e.preventDefault();
        const url = `/api/paiement-devis/impression?start=${devisStart}&end=${devisEnd}`;
        window.open(url, '_blank', 'width=800,height=900,scrollbars=yes');
    });

    $('#devisModal').on('show.bs.modal', function (event) {
        const devisId = $(event.relatedTarget).data('devis-id');
        const $container = $('#devisPreviewContainer');

        $container.html('<p class="text-muted">Chargement du devis...</p>');

        $.getJSON(`/api/devis/${devisId}/preview`, function (data) {
            const lignes = data.contenus.map(c => `
      <tr>
        <td>${c.designation
                }</td>
        <td>${c.qte
                }</td>
        <td>${parseFloat(c.montant).toLocaleString('fr-FR')
                } FCFA</td>
        <td>${parseFloat(c.total).toLocaleString('fr-FR')
                } FCFA</td>
      </tr>
    `).join('');

            const total = parseFloat(data.montant).toLocaleString('fr-FR') + ' FCFA';
            const reste = parseFloat(data.reste).toLocaleString('fr-FR') + ' FCFA';
            const date = new Date(data.date).toLocaleDateString('fr-FR');

            $container.html(`
                <div class="card shadow-sm">
                    <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                        <h5 class="text-primary mb-1">FACTURE N° <strong>${String(data.id).padStart(4, '0')
                                }</strong></h5>
                        <p class="mb-0"><strong>Date :</strong> ${date}</p>
                        </div>
                        <div class="text-end">
                        <h6 class="mb-1">Patient :</h6>
                        <p class="mb-0">${data.patient.nom
                                } ${data.patient.prenom
                                }</p>
                        <p class="text-muted small">${data.patient.telephone || 'Téléphone non renseigné'
                                }</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                            <th>Désignation</th>
                            <th>Qté</th>
                            <th>Prix Unitaire</th>
                            <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.contenus.map(c => `
                            <tr>
                                <td>${c.designation
                                    }</td>
                                <td>${c.qte
                                    }</td>
                                <td>${parseFloat(c.montant).toLocaleString('fr-FR')
                                    } FCFA</td>
                                <td>${parseFloat(c.total).toLocaleString('fr-FR')
                                    } FCFA</td>
                            </tr>
                            `).join('')
                                }
                        </tbody>
                        <tfoot>
                            <tr>
                            <th colspan="3" class="text-end">Total TTC</th>
                            <th>${parseFloat(data.montant).toLocaleString('fr-FR')
                                } FCFA</th>
                            </tr>
                            <tr>
                            <th colspan="3" class="text-end">Reste à payer</th>
                            <th class="text-danger">${parseFloat(data.reste).toLocaleString('fr-FR')
                                } FCFA</th>
                            </tr>
                        </tfoot>
                        </table>
                    </div>

                    <div class="row mt-5">
                        <div class="col-md-6 text-center">
                        <p class="text-muted">Signature du patient</p>
                        <div style="border-top: 1px solid #ccc; width: 80%; margin: 30px auto 0;"></div>
                        </div>
                        <div class="col-md-6 text-center">
                        <p class="text-muted">Cachet de la clinique</p>
                        <div style="border-top: 1px solid #ccc; width: 80%; margin: 30px auto 0;"></div>
                        </div>
                    </div>
                    </div>
                </div>
                `);

        }).fail(() => {
            $container.html('<div class="alert alert-danger">Erreur lors du chargement du devis.</div>');
        });
    });

    function chargerModesPaiement() {
        const $select = $('#modePaiementDevis');
        $select.empty().append('<option disabled selected>Chargement...</option>');

        $.get('/api/modes-paiement', function (modes) {
            $select.empty();
            modes.forEach(mode => {
                if (mode.actif) {
                    $select.append(`<option value="${mode.id
                        }">${mode.libelle
                        } (${mode.type
                        })</option>`);
                }
            });
        }).fail(() => {
            $select.html('<option disabled>Erreur de chargement</option>');
        });
    }

    $('#reglerDevisModal').on('show.bs.modal', function (e) {
        const devisId = $(e.relatedTarget).data('devis-id');
        $(this).data('devis-id', devisId);
        $('#montantRegle').val('');
        chargerModesPaiement();

        // Charger aussi le reste à payer
        $.getJSON(`/api/devis/${devisId}/preview`, function (data) {
            $('#montantRegle').attr('max', data.reste).attr('placeholder', `Max: ${data.reste
                } FCFA`);
        });
    });

    $('#validateFreeDevisModal').on('show.bs.modal', function (e) {
        const devisId = $(e.relatedTarget).data('devis-id');
        $(this).data('devis-id', devisId);  
    });

    $('#montantRegle').on('input', function () {
        const max = parseFloat($(this).attr('max'));
        const val = parseFloat($(this).val());

        if (val > max) {
            $(this).val(max);
        }

        const reste = val < max ? max - (val || 0) : 0;
        $('#resteCalc').text(`Reste après paiement : ${reste.toLocaleString('fr-FR')
            } FCFA`);
    });

    $('#confirmerPaiementDevisBtn').on('click', function () {
        $(this).prop('disabled', true);
        const modal = $('#reglerDevisModal');
        const devisId = modal.data('devis-id');
        const montant = parseFloat($('#montantRegle').val());
        const modeId = $('#modePaiementDevis').val();  
        

        if (!montant || montant <= 0 || !modeId) {
            showToastModal({ message: 'Veuillez renseigner un montant et un mode de paiement.', type: 'warning' });
            return;
        }

        $.ajax({
            url: `/api/devis/${devisId}/payer`,
            method: 'POST',
            data: {
                montant: montant,
                modeId: modeId
            },
            success: function () {
                showToastModal({ message: 'Paiement enregistré avec succès.', type: 'success' });
                
                modal.modal('hide');
                $('#montantRegle').val('');
                $('#resteCalc').text(''); 

                // Recharge les données
                if (typeof devisTable !== 'undefined')
                    devisTable.ajax.reload();

                if (typeof paiementsTable !== 'undefined')
                    paiementsTable.ajax.reload();
                
            },
            error: function () { 
                showToastModal({ message: 'Erreur lors de l’enregistrement du paiement.', type: 'error' });
                
            }
        });$(this).prop('disabled', false);
    });

    $('#validateFreeDevisBtn').on('click', function () {
        $(this).prop('disabled', true);
        const modal = $('#validateFreeDevisModal');
        const devisId = modal.data('devis-id');  

        $.ajax({
            url: `/api/devis/${devisId}/payer`,
            method: 'POST', 
            success: function () {
                showToastModal({ message: 'Facture vide validée.', type: 'success' });
                modal.modal('hide');
                $('#montantRegle').val('');
                $('#resteCalc').text('');

                // Recharge les données
                if (typeof devisTable !== 'undefined')
                    devisTable.ajax.reload();

                if (typeof paiementsTable !== 'undefined')
                    paiementsTable.ajax.reload();

            },
            error: function () {
                showToastModal({ message: 'Erreur lors de la validation.', type: 'error' });
            }
        });$(this).prop('disabled', false);
    });

    $('#devisTableTypeSelect').on("change", () => { 
        const url = $('#devisTableTypeSelect option:selected').val() == "all" ? '/api/devis/all' : '/api/devis/impayes'

        devisTable.ajax.url(url).load();
    }) 

    window.printDevisModal = function () {
        const content = document.getElementById('devisPreviewContainer').innerHTML;
        const style = `
    <style>
      body { font-family: sans-serif; margin: 30px; color: #333; }
      table { width: 100%; border-collapse: collapse; margin-top: 20px; }
      th, td { border: 1px solid #ccc; padding: 8px; }
      .text-end { text-align: right; }
      .text-center { text-align: center; }
    </style>
  `;

        const printWindow = window.open('', '', 'width=900,height=700');
        printWindow.document.write(`
    <html>
      <head><title>Impression Devis</title>${style}</head>
      <body>${content}</body>
    </html>
  `);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };


}); 
