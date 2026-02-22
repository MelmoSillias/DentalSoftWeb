$(function () { // Variables globales pour les filtres de date
    let devisStart = moment().format('YYYY-MM-DD');
    let devisEnd = moment().format('YYYY-MM-DD');
    let AdevisStart = moment().startOf('month').format('YYYY-MM-DD');
    let AdevisEnd = moment().endOf('month').format('YYYY-MM-DD');
    let devisTable;
    let paiementsTable;
    let devisCache = [];
    let paiementsCache = [];

    const viewStorageKey = 'caisse.view';
    let activeView = (localStorage.getItem(viewStorageKey) || 'overview');
    const devisUrl = $('#devisTableTypeSelect option:selected').val() == "all" ? '/api/devis' : '/api/devis/unpaid'

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
                devisCache = Array.isArray(json) ? json : [];
                const totalReste = json.reduce((sum, d) => sum + (parseFloat(d.reste) || 0), 0);
                $('#devisCount').text(json.length);
                $('.js-total-restant').text(`${totalReste.toLocaleString('fr-FR')} FCFA`);

                if ($('#facturesCount').length) {
                    $('#facturesCount').text(json.length);
                }
                if ($('#facturesTotalRestant').length) {
                    $('#facturesTotalRestant').text(`${totalReste.toLocaleString('fr-FR')} FCFA`);
                }

                if (activeView === 'factures') {
                    renderFacturesView();
                }
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
                    const modifiable = row.montant === row.reste && !isRegle
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
            url: '/js/utils/datatables_fr.json'
        },
        order: [
            [0, 'asc']
            
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
        $.get(`/api/consultations/${consultId}/facture`, function (lines) {
            // lines = [ { dent, type, prix, quantite, description, idLigne }, ... ]
            if (!lines.length) {
                $('#factureLinesContainer').append(createFactureLineBlock());
            } else {
                lines.forEach(l => {
                    const blk = createFactureLineBlock(l);
                    $('#factureLinesContainer').append(blk);
                });
            }
            recalcFactureTotal();

        });
    }

      function uniqueId(prefix = 'id') {
        return `${prefix}_${Date.now()}_${Math.floor(Math.random() * 1000)}`;
    }

    const soinsList = [
        'Consultation',
        'Détartrage',
        'Extraction',
        'Remplissage',
        'Composite',
        'Amalgame',
        'Traitement de canal',
        'Traumatisme',
        'Couronne',
        'Blanchiment',
        'Radio',
        'Prothèse',
        'Orthodontie',
        'Chirurgie'
    ];

    function buildSoinOptions(selected = '') {
        return soinsList.map(soin => {
            const sel = soin === selected ? 'selected' : '';
            return `<option value="${soin}" ${sel}>${soin}</option>`;
        }).join('');
    }

    // Fonction de création d’un bloc « ligne de facture » (adaptée de createActeBlock)
    function createFactureLineBlock(data = {}) {
        const uid = uniqueId('ligne'); // même fonction uniqueId
        const $blk = $(`
    <div class="ligne-facture mb-3 border p-2" id="${uid}">
            <div class="row gx-2"> 
                    <div class="col-md-8">
                        <div class="row gx-2">
                            <div class="col-md-6">
                                <label>Dent</label>
                                <input type="text" class="form-control ligne-dent" value="${data.dent || ''}">
                            </div>
                            <div class="col-md-6">
                                <label>Acte / Soin</label>
                                <select class="form-control ligne-type">${buildSoinOptions(data.type || data.designation || '')}</select>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <label>Description</label>
                            <textarea class="form-control ligne-desc" rows="2">${data.description || data.designation || ''}</textarea>
                        </div>
                    </div>
                    <div class="col-md-4 align-items-end">
                        <div class="col-12">
                            <label>Prix (FCFA)</label>
                            <input type="number" step="0.01" class="form-control ligne-prix" value="${data.prix || data.montant || 0}">
                        </div>
                        <div class="col-6 mb-3">
                            <label>Qté</label>
                            <input type="number" min="1" class="form-control ligne-qte" value="${data.quantite || 1}">
                        </div>
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
                dent: $(this).find('.ligne-dent').val(),
                type: $(this).find('.ligne-type').val(),
                prix: parseFloat($(this).find('.ligne-prix').val()) || 0,
                quantite: parseInt($(this).find('.ligne-qte').val()) || 1,
                description: $(this).find('.ligne-desc').val() || ''
                // ajoutez éventuellement l’ID de la ligne si vous en avez besoin
            });
        });

        $.ajax({
            url: `/api/consultations/${consultId}/facture/update`, // stockez-le dans une variable globale
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
    $('#devisDateRange, #devisDateRangeAlt').daterangepicker({
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
        if (paiementsTable) {
            paiementsTable.ajax.reload();
        }
    });

    $('#AlldevisDateRange, #AlldevisDateRangeAlt').daterangepicker({
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
    paiementsTable = $('#paiementsDevisTable').DataTable({
        ajax: {
            url: '/api/devis/payments',
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
            url: '/js/utils/datatables_fr.json'
        },
        order: [
            [0, 'asc']
        ]
    });

    // === 4. Total recettes dynamiques ===
    paiementsTable.on('draw', function () {
        const rows = paiementsTable.rows({ search: 'applied' }).data().toArray();
        paiementsCache = Array.isArray(rows) ? rows : [];
        const total = paiementsCache.reduce((sum, r) => sum + (parseFloat(r.montant) || 0), 0);
        $('.js-recette-total').text(`${total.toLocaleString('fr-FR')} FCFA`);

        if ($('#paiementsCount').length) {
            $('#paiementsCount').text(paiementsCache.length);
        }
        if ($('#paiementsTotal').length) {
            $('#paiementsTotal').text(`${total.toLocaleString('fr-FR')} FCFA`);
        }

        if (activeView === 'paiements') {
            renderPaiementsView();
        }
    });

    // Paiement unique (table + cartes)
    $(document).on('click', '.imprimer-paiement-btn', function (e) {
        e.preventDefault();
        const paiementId = $(this).data('id');
        window.open(`/api/payments/${paiementId}/print`, '_blank', 'width=800,height=900,scrollbars=no');
    });

    $(document).on('click', '.imprimer-ticket-btn', function (e) {
        e.preventDefault();
        const paiementId = $(this).data('id');
        const url = `/api/receipts/${paiementId}/print`;
        window.open(url, '_blank', 'width=800,height=900,scrollbars=no');
    });

    $('#imprimerPaiementsBtn, #imprimerPaiementsBtnAlt').on('click', function (e) {
        e.preventDefault();
        const url = `/api/payments/print?start=${devisStart}&end=${devisEnd}`;
        window.open(url, '_blank', 'width=800,height=900,scrollbars=yes');
    });

    
    function chargerModesPaiement() {
        const $select = $('#modePaiementDevis');
        $select.empty().append('<option disabled selected>Chargement...</option>');

        $.get('/api/payment-methods', function (modes) {
            $select.empty();
            modes.forEach(mode => {
                if (mode.actif) {
                    $select.append(`<option value="${mode.id
                        }">${mode.libelle
                        }</option>`);
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

        const now = new Date();
        $('#paiementDate').val(now.toISOString().slice(0, 10));
        $('#paiementTime').val(now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false }));

        // Charger aussi le reste à payer
        $.getJSON(`/api/devis/${devisId}`, function (data) {
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
        const date = $('#paiementDate').val();
        const time = $('#paiementTime').val();
        

        if (!montant || montant <= 0 || !modeId || !date || !time) {
            showToastModal({ message: 'Veuillez renseigner la date, l\'heure, le montant et le mode de paiement.', type: 'warning' });
            return;
        }

        $.ajax({
            url: `/api/devis/${devisId}/pay`,
            method: 'POST',
            data: {
                montant: montant,
                modeId: modeId,
                date: date,
                time: time
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
            url: `/api/devis/${devisId}/pay`,
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

    $('#devisTableTypeSelectAlt').val($('#devisTableTypeSelect').val());

    $('#devisTableTypeSelect, #devisTableTypeSelectAlt').on("change", function () {
        const val = $(this).val();
        $('#devisTableTypeSelect, #devisTableTypeSelectAlt').val(val);
        const url = val == "all" ? '/api/devis' : '/api/devis/unpaid';
        devisTable.ajax.url(url).load();
    }) 

    // ===== Gestion des vues (boutons flottants) =====
    function setActiveView(view) {
        const normalized = (['overview', 'factures', 'paiements'].indexOf(view) !== -1) ? view : 'overview';
        activeView = normalized;
        localStorage.setItem(viewStorageKey, normalized);

        $('#caisseViewOverview').toggleClass('d-none', normalized !== 'overview');
        $('#caisseViewFactures').toggleClass('d-none', normalized !== 'factures');
        $('#caisseViewPaiements').toggleClass('d-none', normalized !== 'paiements');

        $('.caisse-view-btn').each(function () {
            const isActive = ($(this).data('view') === normalized);
            $(this)
                .toggleClass('btn-primary', isActive)
                .toggleClass('btn-outline-primary', !isActive);
        });

        if (normalized === 'overview') {
            // DataTables peut lever une exception (ResizeObserver.observe) si le wrapper n'est pas encore prêt.
            safeAdjustDataTable(devisTable);
            safeAdjustDataTable(paiementsTable);
        }

        if (normalized === 'factures') {
            renderFacturesView();
        }

        if (normalized === 'paiements') {
            renderPaiementsView();
        }
    }

    $(document).on('click', '.caisse-view-btn', function () {
        setActiveView($(this).data('view'));
    });

    function safeAdjustDataTable(dt) {
        if (!dt || !dt.settings) return;
        const settings = dt.settings();
        if (!settings || !settings.length) return;
        const wrapper = settings[0] && settings[0].nTableWrapper;
        if (!wrapper || wrapper.nodeType !== 1) return;
        if (!document.body.contains(wrapper)) return;

        // Laisse le DOM se stabiliser après show/hide
        setTimeout(function () {
            try {
                dt.columns.adjust();
            } catch (e) {
                // On évite de casser la page : l'ajustement est cosmétique.
                console.warn('DataTables adjust skipped:', e);
            }
        }, 0);
    }

    function escapeHtml(value) {
        const safe = (value === undefined || value === null) ? '' : value;
        return String(safe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatFcfa(amount) {
        const n = parseFloat(amount);
        if (!isFinite(n)) return '0 FCFA';
        return `${n.toLocaleString('fr-FR')} FCFA`;
    }

    function computeDevisStatus(row) {
        const montant = parseFloat(row.montant) || 0;
        const reste = parseFloat(row.reste) || 0;

        if (row.isRegle && reste === 0) {
            return { label: 'Payé', badgeClass: 'badge-success' };
        }
        if (!row.isRegle && reste === 0) {
            return { label: 'Vide non validé', badgeClass: 'badge-secondary' };
        }
        if (reste === montant) {
            return { label: 'Impayé', badgeClass: 'badge-danger' };
        }
        return { label: 'Partiellement payé', badgeClass: 'badge-warning' };
    }

    function buildDevisCard(row) {
        const patient = (row.patient && typeof row.patient === 'object')
            ? `${row.patient.nom} ${row.patient.prenom}`
            : (row.patient || '—');
        const telephone = row.telephone || ((row.patient && row.patient.telephone) ? row.patient.telephone : '') || '';
        const dateStr = row.date ? new Date(row.date).toLocaleDateString('fr-FR') : '—';
        const status = computeDevisStatus(row);
        const montant = formatFcfa(row.montant);
        const reste = formatFcfa(row.reste);

        const statut = row.statut;
        const isRegle = statut == 1;
        const modifiable = row.montant === row.reste && !isRegle;
        const targetModal = (!row.isRegle && parseFloat(row.reste) === 0) ? '#validateFreeDevisModal' : '#reglerDevisModal';

        const reglerButton = isRegle ? '' : `
            <a href="#" class="btn btn-sm btn-success mr-2 regler-devis-btn" data-toggle="modal"
                data-target="${targetModal}" data-devis-id="${escapeHtml(row.id)}">
                <i class="fas fa-hand-holding-usd"></i>
            </a>`;

        const modButton = modifiable ? `
            <a href="#" class="btn btn-sm btn-secondary mr-2 mod-devis-btn" data-toggle="modal"
                data-target="#modifyFactureModal" data-devis-id="${escapeHtml(row.id)}" data-consult-id="${escapeHtml(row.consultation)}">
                <i class="fas fa-pencil-alt"></i>
            </a>` : '';

        const hidePreview = (parseFloat(row.montant) === 0 && parseFloat(row.reste) === 0) ? 'd-none' : '';
        const previewButton = `
            <a href="#" class="btn btn-sm btn-primary preview-devis-btn ${hidePreview}" data-toggle="modal"
                data-target="#devisModal" data-devis-id="${escapeHtml(row.id)}">
                <i class="fas fa-eye"></i>
            </a>`;

        return `
            <div class="col-lg-4 col-md-6 mb-3 caisse-card-grid">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-muted">${escapeHtml(dateStr)}</div>
                                <div class="h6 mb-1">${escapeHtml(patient)}</div>
                                <div class="small text-muted">${escapeHtml(telephone)}</div>
                            </div>
                            <span class="badge ${status.badgeClass}">${escapeHtml(status.label)}</span>
                        </div>
                        <hr class="my-2"/>
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="small text-muted">Montant</div>
                                <div class="font-weight-bold">${escapeHtml(montant)}</div>
                            </div>
                            <div class="text-right">
                                <div class="small text-muted">Reste</div>
                                <div class="font-weight-bold text-danger">${escapeHtml(reste)}</div>
                            </div>
                        </div>
                        <div class="mt-3 d-flex align-items-center">
                            ${reglerButton}
                            ${modButton}
                            ${previewButton}
                        </div>
                    </div>
                </div>
            </div>`;
    }

    function renderFacturesView() {
        const $accordion = $('#facturesAccordion');
        if (!$accordion.length) return;

        const list = Array.isArray(devisCache) ? devisCache : [];
        if (!list.length) {
            $('#facturesEmptyMessage').removeClass('d-none');
            $accordion.empty();
            $('#facturesCount').text('0');
            $('#facturesTotalRestant').text('0 FCFA');
            $('#facturesBreakdown').text('—');
            return;
        }
        $('#facturesEmptyMessage').addClass('d-none');

        const groups = {
            impaye: [],
            partiel: [],
            paye: []
        };

        list.forEach(row => {
            const status = computeDevisStatus(row);
            if (status.label === 'Impayé') {
                groups.impaye.push(row);
            } else if (status.label === 'Partiellement payé') {
                groups.partiel.push(row);
            } else {
                groups.paye.push(row);
            }
        });

        const totalRestant = list.reduce((sum, r) => sum + (parseFloat(r.reste) || 0), 0);
        $('#facturesCount').text(String(list.length));
        $('#facturesTotalRestant').text(formatFcfa(totalRestant));
        $('#facturesBreakdown').text(`${groups.impaye.length}/${groups.partiel.length}/${groups.paye.length}`);

        const sections = [
            { key: 'impaye', title: 'Impayées', badge: 'badge-danger' },
            { key: 'partiel', title: 'Partiellement payées', badge: 'badge-warning' },
            { key: 'paye', title: 'Payées / autres', badge: 'badge-success' }
        ];

        $accordion.html(sections.map((s, idx) => {
            const collapseId = `facturesCollapse_${s.key}`;
            const headingId = `facturesHeading_${s.key}`;
            const open = idx === 0 ? 'show' : '';
            const expanded = idx === 0 ? 'true' : 'false';
            const cards = groups[s.key].map(buildDevisCard).join('');
            const body = cards ? `<div class="row">${cards}</div>` : `<div class="text-muted">Aucun élément</div>`;
            return `
                <div class="card mb-2">
                    <div class="card-header" id="${headingId}">
                        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#${collapseId}" aria-expanded="${expanded}" aria-controls="${collapseId}">
                            <span class="font-weight-bold">${escapeHtml(s.title)}</span>
                            <span class="badge ${s.badge} ml-2">${groups[s.key].length}</span>
                        </button>
                    </div>
                    <div id="${collapseId}" class="collapse ${open}" aria-labelledby="${headingId}">
                        <div class="card-body">${body}</div>
                    </div>
                </div>`;
        }).join(''));
    }

    function renderMiniChart(list) {
        const $chart = $('#paiementsMiniChart');
        if (!$chart.length) return;

        if (!Array.isArray(list) || !list.length) {
            $chart.html('<div class="small text-muted">Aucune donnée</div>');
            return;
        }

        const byDay = {};
        list.forEach(p => {
            const dayKey = p.date ? moment(p.date).format('YYYY-MM-DD') : '—';
            const val = parseFloat(p.montant) || 0;
            byDay[dayKey] = (byDay[dayKey] || 0) + val;
        });

        const keys = Object.keys(byDay).sort((a, b) => a.localeCompare(b)).slice(-7);
        const values = keys.map(k => byDay[k]);
        const max = Math.max.apply(null, values.concat([1]));
        $chart.html(keys.map((day) => {
            const val = byDay[day];
            const pct = Math.round((val / max) * 100);
            return `
                <div class="mb-2">
                    <div class="d-flex justify-content-between small text-muted">
                        <span>${escapeHtml(day)}</span>
                        <span>${escapeHtml(formatFcfa(val))}</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: ${pct}%;" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>`;
        }).join(''));
    }

    function buildPaiementItem(row) {
        const date = row.date ? new Date(row.date) : null;
        const dateStr = date ? (date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })) : '—';
        const patient = row.patient || '—';
        const telephone = row.telephone || '';
        const montant = formatFcfa(row.montant);
        const mode = row.mode || '—';

        const isDevis = row.type === 'devis';
        const btnClass = isDevis ? 'btn-outline-secondary' : 'btn-info';
        const iconClass = isDevis ? 'fa-print' : 'fa-ticket-alt';
        const printClass = isDevis ? 'imprimer-paiement-btn' : 'imprimer-ticket-btn';

        return `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small text-muted">${escapeHtml(dateStr)}</div>
                        <div class="font-weight-bold">${escapeHtml(patient)}</div>
                        <div class="small text-muted">${escapeHtml(telephone)} • ${escapeHtml(mode)}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-weight-bold">${escapeHtml(montant)}</div>
                        <a href="#" class="btn btn-sm ${btnClass} ${printClass} mt-2" data-id="${escapeHtml(row.pId)}">
                            <i class="fas ${iconClass}"></i>
                        </a>
                    </div>
                </div>
            </div>`;
    }

    function renderPaiementsView() {
        const $accordion = $('#paiementsAccordion');
        if (!$accordion.length) return;

        const list = Array.isArray(paiementsCache) ? paiementsCache : [];
        if (!list.length) {
            $('#paiementsEmptyMessage').removeClass('d-none');
            $accordion.empty();
            $('#paiementsCount').text('0');
            $('#paiementsTotal').text('0 FCFA');
            renderMiniChart([]);
            return;
        }
        $('#paiementsEmptyMessage').addClass('d-none');

        const total = list.reduce((sum, r) => sum + (parseFloat(r.montant) || 0), 0);
        $('#paiementsCount').text(String(list.length));
        $('#paiementsTotal').text(formatFcfa(total));
        renderMiniChart(list);

        const byMode = {};
        list.forEach(p => {
            const key = p.mode || 'Autre';
            byMode[key] = byMode[key] || [];
            byMode[key].push(p);
        });

        const modes = Object.keys(byMode).sort((a, b) => a.localeCompare(b, 'fr'));
        $accordion.html(modes.map((mode, idx) => {
            const key = `paiements_${idx}`;
            const collapseId = `paiementsCollapse_${key}`;
            const headingId = `paiementsHeading_${key}`;
            const open = idx === 0 ? 'show' : '';
            const expanded = idx === 0 ? 'true' : 'false';
            const items = byMode[mode].map(buildPaiementItem).join('');
            return `
                <div class="card mb-2">
                    <div class="card-header" id="${headingId}">
                        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#${collapseId}" aria-expanded="${expanded}" aria-controls="${collapseId}">
                            <span class="font-weight-bold">${escapeHtml(mode)}</span>
                            <span class="badge badge-success ml-2">${byMode[mode].length}</span>
                        </button>
                    </div>
                    <div id="${collapseId}" class="collapse ${open}" aria-labelledby="${headingId}">
                        <div class="card-body">
                            <div class="list-group">${items}</div>
                        </div>
                    </div>
                </div>`;
        }).join(''));
    }

    // Applique la vue persistée une fois tout initialisé
    if ($('.caisse-view-btn').length) {
        setActiveView(activeView);
    }

    $('#devisModal').on('show.bs.modal', function (event) {
        const devisId = $(event.relatedTarget).data('devis-id');
        const $container = $('#devisPreviewContainer');

        $container.html('<p class="text-muted">Chargement du devis...</p>');
        $container.data('devis-id', devisId);

        $.getJSON(`/api/devis/${devisId}`, function (data) {
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

            const watermarkUrl = `${window.location.origin}/img/logo.png`;
            $container.html(`
                <style>
                    .paper-with-watermark { position: relative; overflow: hidden; }
                    .paper-with-watermark::before {
                        content: '';
                        position: absolute;
                        inset: 0;
                        background: url('${watermarkUrl}') center center no-repeat;
                        background-size: 70%;
                        opacity: 0.12;
                        pointer-events: none;
                        z-index: 0;
                    }
                    .paper-with-watermark .paper-body { position: relative; z-index: 1; }
                </style>
                <div class="paper-with-watermark">
                    <div class="paper-body">
                        <div class="row mb-3 d-flex align-items-center no-wrap justify-content-between">
                            <div class="col-8 header">
                                <img src="/img/header-big.jpeg" alt="Cabinet Dentaire Orodent" style="max-height:90px; width:100%; object-fit:contain;">
                            </div>

                            <div class="col-4 text-end">
                                <p class="doc-title">Facture N° ${String(data.id).padStart(4, '0')}</p>
                                <p class="doc-date">Date : ${date}</p>
                            </div>
                        </div>
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="patient-line">
                                    Patient :
                                    <strong>${data.patient.nom} ${data.patient.prenom}</strong>
                                    &nbsp;&nbsp;|&nbsp;&nbsp;
                                    Telephone :
                                    ${data.patient.telephone || 'Non renseigné'}
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
                    </div>
                </div>
                `);

        }).fail(() => {
            $container.html('<div class="alert alert-danger">Erreur lors du chargement du devis.</div>');
        });
    });


    window.printDevisModal = function () {
        const devisId = $('#devisPreviewContainer').data('devis-id');
        if (!devisId) return;
        // En caisse on imprime comme facture
        window.open(`/api/invoices/${devisId}/print`, '_blank', 'width=900,height=900,scrollbars=yes');
    };


}); 
