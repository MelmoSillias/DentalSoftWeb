$(function() {
    // IDs injectés par Twig
    const ficheId = window.ficheId;
    const consultId = window.consultId;

    // Flags de modification par section
    let isMotifModified = false;
    let isExamensModified = false;
    let isTraitementsModified = false;
    let isDevisModified = false;
    let isConsultModified = false;
    let isOrdoModified = false;

    const sectionMap = {
        motif: 'Motif & Histoire',
        examens: 'Examens',
        traitements: 'Traitements & Documents',
        devis: 'Devis',
        consult: 'Consultation en cours'
    };

    const savedSections = {
        motif: false,
        examens: false,
        traitements: false,
        devis: false,
        consult: false,
        ordonnances: false
    };
    const totalSections = Object.keys(savedSections).length;

    const AUTOSAVE_DELAY = 7000;
    let autosaveTimer = null;
    let lastSavedAt = null;
    let savingCount = 0;
    let currentDevisId = null;

    const $saveAllBtn = $('#btnSaveAll');

    function setSectionBadge(section, state) {
        const $badge = $(`[data-section-badge="${section}"]`);
        if (!$badge.length) return;
        $badge.removeClass('bg-warning bg-success bg-info bg-secondary text-white text-dark');
        if (state === 'dirty') {
            $badge.text('•').addClass('bg-warning text-dark').show();
        } else if (state === 'saving') {
            $badge.html('<i class="fas fa-spinner fa-spin"></i>').addClass('bg-info text-white').show();
        } else if (state === 'saved') {
            $badge.text('✓').addClass('bg-success text-white').show();
            setTimeout(() => $badge.fadeOut(300), 900);
        } else {
            $badge.text('').hide();
        }
    }

    function formatTime(dateObj) {
        if (!dateObj) return 'Jamais enregistré';
        return `Dernière sauvegarde à ${dateObj.toLocaleTimeString()}`;
    }

    function updateGlobalSaveStatus() {
        const $alert = $('#saveStatusIndicator .alert');
        const $dot = $('#saveStatusDot');
        $alert.removeClass('alert-secondary alert-warning alert-info alert-success');
        $dot.removeClass('bg-secondary bg-warning bg-info bg-success');

        const dirty = isMotifModified || isExamensModified || isTraitementsModified || isDevisModified || isConsultModified || isOrdoModified;
        let text, cls, dotCls;

        if (savingCount > 0) {
            text = 'Sauvegarde en cours…';
            cls = 'alert-info';
            dotCls = 'bg-info';
        } else if (dirty) {
            text = 'Modifications en cours';
            cls = 'alert-warning';
            dotCls = 'bg-warning';
        } else {
            const savedCount = Object.values(savedSections).filter(v => v).length;
            if (savedCount === 0) {
                text = 'Aucune modification';
                cls = 'alert-secondary';
                dotCls = 'bg-secondary';
            } else if (savedCount < totalSections) {
                text = 'Partiellement mis à jour';
                cls = 'alert-info';
                dotCls = 'bg-info';
            } else {
                text = 'Tout est sauvegardé';
                cls = 'alert-success';
                dotCls = 'bg-success';
            }
        }

        $alert.addClass(cls);
        $dot.addClass(dotCls);
        $('#saveStatusText').text(text);
        $('#lastSavedText').text(formatTime(lastSavedAt));
        $saveAllBtn.prop('disabled', !dirty && savingCount === 0);
        refreshUnsavedList();
    }

    function getUnsavedSections() {
        const list = [];
        if (isMotifModified) list.push(sectionMap.motif);
        if (isExamensModified) list.push(sectionMap.examens);
        if (isTraitementsModified) list.push(sectionMap.traitements);
        if (isDevisModified) list.push(sectionMap.devis);
        if (isConsultModified) list.push(sectionMap.consult);
        if (isOrdoModified) list.push('Ordonnances');
        return list;
    }

    function refreshUnsavedList() {
        const $list = $('#unsavedSectionsList');
        if (!$list.length) return;
        const unsaved = getUnsavedSections();
        if (!unsaved.length) {
            $list.text('Aucune section non sauvegardée.');
        } else {
            $list.html(unsaved.map(s => `• ${s}`).join('<br>'));
        }
    }

    function canCloture() {
        return !(isMotifModified || isExamensModified || isTraitementsModified || isDevisModified || isConsultModified);
    }

    function scheduleAutoSave() {
        clearTimeout(autosaveTimer);
        if (canCloture()) return;
        autosaveTimer = setTimeout(() => {
            saveAllSections({ reason: 'autosave', silent: true });
        }, AUTOSAVE_DELAY);
    }

    function markDirty(section) {
        if (section === 'motif') isMotifModified = true;
        if (section === 'examens') isExamensModified = true;
        if (section === 'traitements') isTraitementsModified = true;
        if (section === 'devis') isDevisModified = true;
        if (section === 'consult') isConsultModified = true;
        savedSections[section] = false;
        setSectionBadge(section, 'dirty');
        updateGlobalSaveStatus();
        scheduleAutoSave();
    }

    // Watchers sur chaque formulaire pour détecter les changements
    $('#motifSoinsForm').on('input change', 'textarea', function() {
        markDirty('motif');
    });
    $('#examensForm, #toothContainer').on('input change', 'textarea', function() {
        markDirty('examens');
    });
    $('#traitementsDocumentsForm').on('input change', 'input, textarea', function() {
        markDirty('traitements');
    });
    $('#devisForm').on('input change', 'input, textarea', function() {
        markDirty('devis');
    });

    $('#btnPrintDevis').on('click', function() {
        console.log('Impression Devis ID :', currentDevisId);
        if (!currentDevisId) {
            if (typeof showToastModal === 'function') {
                showToastModal({ message: 'Aucun devis à imprimer pour ce patient.', type: 'warning', duration: 2500 });
            }
            return;
        }
        printDevisDirect(currentDevisId);
    });
    $('#consultationEnCoursForm').on('input change', 'input, textarea, select', function() {
        markDirty('consult');
    });

    $('#ordonnanceForm').on('input change', 'input, textarea', function() {
        markDirty('ordonnances');
    });

    $('#btnOpenAddOrdonnance').on('click', function() {
        $('#ordoDate').val(new Date().toISOString().slice(0,10));
        $('#ordoMedecin').val($('#medecin option:selected').text() || '');
        $('#ordoNote').val('');
        $('#ordoLinesContainer').empty().append(createOrdoLineBlock());
        $('#modalAddOrdonnance').modal('show');
    });

    $('#btnAddOrdoLine').on('click', function() {
        $('#ordoLinesContainer').append(createOrdoLineBlock());
    });

    $('#btnSaveOrdonnance').on('click', function() {
        sendOrdonnanceCreate();
    });

    $(document).on('click', '.btn-print-ordo', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        window.open(`/api/ordonnance/${id}/print`, '_blank', 'width=800,height=900');
    });

    $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
        scheduleAutoSave();
    });

    function startSaving(section) {
        savingCount += 1;
        if (section) setSectionBadge(section, 'saving');
        updateGlobalSaveStatus();
    }

    function stopSaving(section, success = true) {
        savingCount = Math.max(0, savingCount - 1);
        if (section) {
            setSectionBadge(section, success ? 'saved' : 'dirty');
        }
        if (success) {
            lastSavedAt = new Date();
        }
        updateGlobalSaveStatus();
    }

    async function saveAllSections({ reason = 'manual', silent = false } = {}) {
        const actions = [];
        if (isMotifModified) actions.push(() => sendMotifUpdate({ silent }));
        if (isExamensModified) actions.push(() => sendExamensUpdate({ silent }));
        if (isTraitementsModified) actions.push(() => sendTraitementsUpdate({ silent }));
        if (isDevisModified) actions.push(() => sendDevisUpdate({ silent }));
        if (isConsultModified) actions.push(() => sendConsultUpdate({ silent }));
        if (isOrdoModified) actions.push(() => sendOrdonnanceCreate({ silent }));

        if (!actions.length) return;

        for (const fn of actions) {
            // Enchaîne les sauvegardes pour limiter la charge
            /* eslint-disable no-await-in-loop */
            try {
                await fn();
            } catch (e) {
                console.error('Sauvegarde échouée', e);
                if (typeof showToastModal === 'function') {
                    showToastModal({ message: 'Une section n\'a pas pu être sauvegardée', type: 'error', duration: 3500 });
                }
                break;
            }
            /* eslint-enable no-await-in-loop */
        }
    }

    // Empêche la fermeture/rafraîchissement si modifs non sauvées
    function askBeforeUnload(e) {
        if (!canCloture()) {
            e.preventDefault();
            e.returnValue = '';
        }
    }
    window.addEventListener('beforeunload', askBeforeUnload);

    // Bouton Retour
    $('#btnRetour').on('click', function() {
        if (!canCloture()) {
            $('#modalQuitConfirm').modal('show');
        } else {
            window.history.back();
        }
    });
    $('#btnQuitConfirmed').on('click', function() {
        $('#modalQuitConfirm').modal('hide');
        window.history.back();
    });

    $('#modalQuitConfirm').on('show.bs.modal', refreshUnsavedList);

    $saveAllBtn.on('click', function() {
        saveAllSections();
    });

    // Chargement initial des données
    function loadData() {
        $.getJSON(`/api/fiches/${ficheId}/consultations/${consultId}/json`, function(data) {
            // Motif & Histoire
            $('#motif').val(data.fiche.motif);
            $('#histoireMaladie').val(data.fiche.histoireMaladie);
            $('#soinsAnterieurs').val(data.fiche.soinsAnterieurs);

            // Examens généraux
            $('#exoInspection').val(data.fiche.exoInspection);
            $('#exoPalpation').val(data.fiche.exoPalpation);
            $('#endoInspection').val(data.fiche.endoInspection);
            $('#endoPalpation').val(data.fiche.endoPalpation);
            $('#occlusion').val(data.fiche.occlusion);
            $('#examenParodontal').val(data.fiche.examenParodontal);
            $('#diagnostic').val(data.fiche.diagnostic);

            // Examens dentaires
            for (const [tooth, result] of Object.entries(data.fiche.examens)) {
                $(`#tooth-${tooth}`).val(result);
            }

            // Traitements & Documents
            $('#traitementUrgence').val(data.fiche.traitementUrgence);
            $('#traitementDentaire').val(data.fiche.traitementDentaire);
            $('#traitementParodontal').val(data.fiche.traitementParodontal);
            $('#traitementOrthodontique').val(data.fiche.traitementOrthodontique);
            $('#autres').val(data.fiche.autres);
            $('#documentsContainer').empty();
            (data.fiche.documents || []).forEach((doc, i) => {
                addDocumentBlock(doc);
            });

            // Devis
            currentDevisId = data.fiche.devis?.id || null;
            console.log('Devis ID chargé :', currentDevisId);
            $('#devisDate').val(data.fiche.devis?.date || '');
            $('#servicesContainer').empty();
            (data.fiche.devis?.contenus || []).forEach(c => {
                const blk = createServiceBlock(c);
                $('#servicesContainer').append(blk);
            });
            updateDevisTotal();

            // Consultation en cours
            $('#noteSeance').val(data.consultation.noteSeance);
            $('#medecin').val(data.consultation.medecin?.id || '');
            $('#infirmier').val(data.consultation.infirmier?.id || '');
            $('#salle').val(data.consultation.salle?.id || '');
            $('#actesContainer').empty();
            data.actes.forEach(a => {
                const blk = createActeBlock(a);
                $('#actesContainer').append(blk);
            });

            // Ordonnances
            loadOrdonnances();

            // Réinitialisation des flags
            isMotifModified = isExamensModified = isTraitementsModified = isDevisModified = isConsultModified = isOrdoModified = false;
            Object.keys(savedSections).forEach(k => savedSections[k] = false);
            ['motif', 'examens', 'traitements', 'devis', 'consult', 'ordonnances'].forEach(sec => setSectionBadge(sec, 'clean'));
            updateGlobalSaveStatus();
        });
    }
    loadData();

    // Collecteurs de données
    function collectMotifData() {
        return {
            motif: $('#motif').val(),
            histoireMaladie: $('#histoireMaladie').val(),
            soinsAnterieurs: $('#soinsAnterieurs').val()
        };
    }

    function collectExamensData() {
        const examensDentaires = {};
        $('#toothContainer .tooth-input').each(function() {
            examensDentaires[$(this).data('tooth')] = $(this).val();
        });
        return Object.assign({}, {
            exoInspection: $('#exoInspection').val(),
            exoPalpation: $('#exoPalpation').val(),
            endoInspection: $('#endoInspection').val(),
            endoPalpation: $('#endoPalpation').val(),
            occlusion: $('#occlusion').val(),
            examenParodontal: $('#examenParodontal').val(),
            diagnostic: $('#diagnostic').val()
        }, { examensDentaires });
    }

    function collectTraitementsData() {
        const data = {
            traitementUrgence: $('#traitementUrgence').val(),
            traitementDentaire: $('#traitementDentaire').val(),
            traitementParodontal: $('#traitementParodontal').val(),
            traitementOrthodontique: $('#traitementOrthodontique').val(),
            autres: $('#autres').val(),
            documents: []
        };
        const formData = new FormData();
        formData.append('data', JSON.stringify(data));

        $('#documentsContainer .document-block').each(function(i) {
            const $blk = $(this);
            data.documents.push({
                libelle: $blk.find('.doc-libelle').val(),
                dateDossier: $blk.find('.doc-date').val(),
                description: $blk.find('.doc-description').val(),
                url: $blk.data('existing-url') || null
            });
            const file = $blk.find('input[type="file"]')[0]?.files[0];
            if (file) {
                formData.append(`documentsFiles[${i}]`, file);
            }
        });
        formData.set('data', JSON.stringify(data));
        return formData;
    }

    function collectDevisData() {
        const contenus = [];
        $('#servicesContainer .service-block').each(function() {
            const $b = $(this);
            contenus.push({
                designation: $b.find('.service-designation').val(),
                qte: parseInt($b.find('.service-qte').val()) || 1,
                montant: parseFloat($b.find('.service-montant').val()) || 0
            });
        });
        return {
            date: $('#devisDate').val(),
            contenus: contenus
        };
    }

    function collectConsultData() {
        const actes = [];
        $('#actesContainer .acte-block').each(function() {
            const $b = $(this);
            actes.push({
                dent: $b.find('.acte-dent').val(),
                type: $b.find('.acte-type').val(),
                description: $b.find('.acte-desc').val(),
                prix: parseFloat($b.find('.acte-prix').val()) || 0,
                quantite: parseInt($b.find('.acte-qte').val()) || 1
            }); 
        });
        return {
            medecinId: $('#medecin').val(),
            infirmierId: $('#infirmier').val(),
            salleId: $('#salle').val(),
            noteSeance: $('#noteSeance').val(),
            actes: actes
        };
    }

    // --- Ordonnances ---
    function createOrdoLineBlock(data = {}) {
        const $blk = $(`
            <div class="ordo-line border rounded p-2 mb-2">
                <div class="row gx-2">
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control ordo-designation" placeholder="Médicament" value="${data.designation || ''}">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control ordo-posologie" placeholder="Posologie" value="${data.posologie || ''}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="text" class="form-control ordo-frequence" placeholder="Fréquence" value="${data.frequence || ''}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="text" class="form-control ordo-duree" placeholder="Durée" value="${data.duree || ''}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="number" min="1" class="form-control ordo-quantite" placeholder="Qté" value="${data.quantite || ''}">
                    </div>
                    <div class="col-md-8 mb-2">
                        <input type="text" class="form-control ordo-instructions" placeholder="Instructions" value="${data.instructions || ''}">
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ordo-line"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        `);
        $blk.on('click', '.btn-remove-ordo-line', function() { $blk.remove(); });
        $blk.on('input change', 'input, textarea', () => markDirty('ordonnances'));
        return $blk;
    }

    function renderOrdonnances(items) {
        const $container = $('#ordonnancesContainer');
        $container.empty();
        if (!items.length) {
            $container.append('<p class="text-muted">Aucune ordonnance.</p>');
            return;
        }

        items.forEach((o, idx) => {
            const collapseId = `ordo-${o.id}-coll`; 
            const lignes = (o.lignes || []).map(l => `
                <tr>
                    <td>${l.designation || ''}</td>
                    <td>${l.posologie || ''}</td>
                    <td>${l.frequence || ''}</td>
                    <td>${l.duree || ''}</td>
                    <td>${l.quantite || ''}</td>
                </tr>
            `).join('');
            const card = `
                <div class="card mb-2">
                    <div class="card-header d-flex justify-content-between align-items-center" data-toggle="collapse" data-target="#${collapseId}" style="cursor:pointer;">
                        <div>
                            <strong>Ordonnance ${idx + 1}</strong> · ${o.date || ''} · ${o.medecinNom || ''}
                        </div>
                        <button class="btn btn-sm btn-outline-secondary btn-print-ordo" data-id="${o.id}"><i class="fas fa-print"></i></button>
                    </div>
                    <div id="${collapseId}" class="collapse">
                        <div class="card-body">
                            <p class="text-muted mb-2">${o.note || ''}</p>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead><tr><th>Désignation</th><th>Posologie</th><th>Fréquence</th><th>Durée</th><th>Qté</th></tr></thead>
                                    <tbody>${lignes}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $container.append(card);
        });
    }

    function collectOrdonnancePayload() {
        const lignes = [];
        $('#ordoLinesContainer .ordo-line').each(function() {
            const $l = $(this);
            lignes.push({
                designation: $l.find('.ordo-designation').val(),
                posologie: $l.find('.ordo-posologie').val(),
                frequence: $l.find('.ordo-frequence').val(),
                duree: $l.find('.ordo-duree').val(),
                quantite: parseInt($l.find('.ordo-quantite').val()) || null,
                instructions: $l.find('.ordo-instructions').val()
            });
        });
        return {
            date: $('#ordoDate').val(),
            medecinNom: $('#ordoMedecin').val(),
            note: $('#ordoNote').val(),
            lignes
        };
    }

    function loadOrdonnances() {
        $.getJSON(`/api/consultations/${consultId}/ordonnances`, function(items) {
            renderOrdonnances(items || []);
        });
    }

    function sendOrdonnanceCreate({ silent } = {}) {
        const payload = collectOrdonnancePayload();
        if (!payload.lignes.length) {
            if (!silent && typeof showToastModal === 'function') {
                showToastModal({ message: 'Ajoutez au moins une ligne.', type: 'warning' });
            }
            return Promise.resolve();
        }
        startSaving('ordonnances');
        return $.ajax({
            url: `/api/consultations/${consultId}/ordonnances`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload)
        }).done(() => {
            isOrdoModified = false;
            savedSections.ordonnances = true;
            $('#modalAddOrdonnance').modal('hide');
            loadOrdonnances();
            if (!silent && typeof showToastModal === 'function') {
                showToastModal({ message: 'Ordonnance enregistrée.', type: 'success' });
            }
        }).fail(() => {
            if (!silent && typeof showToastModal === 'function') {
                showToastModal({ message: 'Erreur lors de la sauvegarde de l\'ordonnance.', type: 'error' });
            }
        }).always(() => stopSaving('ordonnances'));
    }

    function buildDevisPrintHtml(data, headerUrl) {
        const lignes = (data.contenus || []).map(c => `
            <tr>
                <td>${c.designation}</td>
                <td>${c.qte}</td>
                <td>${parseFloat(c.montant).toLocaleString('fr-FR')} FCFA</td>
                <td>${parseFloat(c.total).toLocaleString('fr-FR')} FCFA</td>
            </tr>
        `).join('');


        const total = parseFloat(data.montant || 0).toLocaleString('fr-FR');
        const reste = parseFloat(data.reste || 0).toLocaleString('fr-FR');
        const date = data.date ? new Date(data.date).toLocaleDateString('fr-FR') : '';

        return `
            <div class="row mb-3 d-flex align-items-center no-wrap justify-content-between">
                <div class="col-8 header">
                    <img src="${headerUrl}" alt="Cabinet Dentaire Orodent" style="max-height:90px; width:100%; object-fit:contain;">
                </div>
                <div class="col-4 text-end">
                    <p class="doc-title">Devis N° ${String(data.id || '').padStart(4, '0')}</p>
                    <p class="doc-date">Date : ${date}</p>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="patient-line">
                        Patient :
                        <strong>${data.patient?.nom || ''} ${data.patient?.prenom || ''}</strong>
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        Telephone : ${data.patient?.telephone || 'Non renseigné'}
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
                            <tbody>${lignes}</tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total TTC</th>
                                    <th>${total} FCFA</th>
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
        `;
    }

    function printDevisDirect(devisId) {
        window.open(`/api/devis/${devisId}/print`, '_blank', 'width=900,height=900,scrollbars=yes');
    }

    function preloadImage(url, timeout = 4000) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const timer = setTimeout(() => {
                img.onload = null;
                img.onerror = null;
                reject(new Error('Image load timeout'));
            }, timeout);
            img.onload = () => {
                clearTimeout(timer);
                resolve();
            };
            img.onerror = () => {
                clearTimeout(timer);
                reject(new Error('Image load error'));
            };
            img.src = url;
        });
    }

    // Envois AJAX vers les nouvelles routes
    function sendMotifUpdate({ silent = false } = {}) {
        const $button = $('#btnSaveMotifSoins');
        $button.prop('disabled', true);
        startSaving('motif');
        return $.ajax({
            url: `/api/fiches/${ficheId}/consultations/${consultId}/motif`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(collectMotifData())
        }).done(function() {
            isMotifModified = false;
            savedSections.motif = true;
            if (!silent) showToastModal({ message: 'Motif & histoire enregistrés', type: 'success', duration: 2500 });
        }).fail(function() {
            if (!silent) showToastModal({ message: 'Erreur sauvegarde motif', type: 'error', duration: 3000 });
        }).always(function(_, status) {
            $button.prop('disabled', false);
            stopSaving('motif', status === 'success');
        });
    }

    function sendExamensUpdate({ silent = false } = {}) {
        const $button = $('#btnSaveExamens');
        $button.prop('disabled', true);
        startSaving('examens');
        return $.ajax({
            url: `/api/fiches/${ficheId}/consultations/${consultId}/examens`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(collectExamensData())
        }).done(function() {
            isExamensModified = false;
            savedSections.examens = true;
            if (!silent) showToastModal({ message: 'Examens enregistrés', type: 'success', duration: 2500 });
        }).fail(function() {
            if (!silent) showToastModal({ message: 'Erreur sauvegarde examens', type: 'error', duration: 3000 });
        }).always(function(_, status) {
            $button.prop('disabled', false);
            stopSaving('examens', status === 'success');
        });
    }

    function sendTraitementsUpdate({ silent = false } = {}) {
        const $button = $('#btnSaveTraitementsDocuments');
        $button.prop('disabled', true);
        startSaving('traitements');
        return $.ajax({
            url: `/api/fiches/${ficheId}/consultations/${consultId}/traitements`,
            method: 'POST',
            processData: false,
            contentType: false,
            data: collectTraitementsData()
        }).done(function() {
            isTraitementsModified = false;
            savedSections.traitements = true;
            if (!silent) showToastModal({ message: 'Traitements et documents enregistrés', type: 'success', duration: 2500 });
        }).fail(function() {
            if (!silent) showToastModal({ message: 'Erreur sauvegarde traitements', type: 'error', duration: 3000 });
        }).always(function(_, status) {
            $button.prop('disabled', false);
            stopSaving('traitements', status === 'success');
        });
    }

    function sendDevisUpdate({ silent = false } = {}) {
        const $button = $('#btnSaveDevis');
        $button.prop('disabled', true);
        startSaving('devis');
        return $.ajax({
            url: `/api/fiches/${ficheId}/consultations/${consultId}/devis`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(collectDevisData())
        }).done(function() {
            isDevisModified = false;
            savedSections.devis = true;
            if (!silent) showToastModal({ message: 'Devis enregistré', type: 'success', duration: 2500 });
        }).fail(function() {
            if (!silent) showToastModal({ message: 'Erreur sauvegarde devis', type: 'error', duration: 3000 });
        }).always(function(_, status) {
            $button.prop('disabled', false);
            stopSaving('devis', status === 'success');
        });
    }

    function sendConsultUpdate({ silent = false } = {}) {
        const $button = $('#btnSaveConsultationEnCours');
        $button.prop('disabled', true);
        startSaving('consult');
        return $.ajax({
            url: `/api/fiches/${ficheId}/consultations/${consultId}`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(collectConsultData())
        }).done(function() {
            isConsultModified = false;
            savedSections.consult = true;
            if (!silent) showToastModal({ message: 'Consultation enregistrée', type: 'success', duration: 2500 });
        }).fail(function() {
            if (!silent) showToastModal({ message: 'Erreur sauvegarde consultation', type: 'error', duration: 3000 });
        }).always(function(_, status) {
            $button.prop('disabled', false);
            stopSaving('consult', status === 'success');
        });
    }

    // Gestion de la clôture
    $('#btnCloturerConsultation').on('click', function() {
        if (!canCloture()) {
            const unsaved = getUnsavedSections().join(', ');
            showToastModal({ message: `Sections non sauvegardées : ${unsaved}`, type: 'warning', duration: 2000 });
            return;
        }
        $('#modalClotureConsultation').modal('show');
    });

    $('#btnConfirmCloture').on('click', function() {
        const $button = $('#btnConfirmCloture');
        $button.prop('disabled', true);

        $.ajax({
            url: `/api/fiches/${ficheId}/consultations/${consultId}/cloture`,
            method: 'POST',
            success: function() {
                showToastModal({ message: 'Consultation clôturée', type: 'success', duration: 3000 });
                window.location.href = '/admin/consultation/en-attente';
            },
            error: function() {
                showToastModal({ message: 'Erreur clôture consultation', type: 'error', duration: 3000 });
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Boutons de sauvegarde
    $('#btnSaveMotifSoins').on('click', sendMotifUpdate);
    $('#btnSaveExamens').on('click', sendExamensUpdate);
    $('#btnSaveTraitementsDocuments').on('click', sendTraitementsUpdate);
    $('#btnSaveDevis').on('click', sendDevisUpdate);
    $('#btnSaveConsultationEnCours').on('click', sendConsultUpdate);

    // Fonctions utilitaires pour blocs dynamiques
    function uniqueId(prefix = 'id') {
        return `${prefix}_${Date.now()}_${Math.floor(Math.random() * 1000)}`;
    }

    // Documents
    $('#btnAddDocument').on('click', () => addDocumentBlock());
    function addDocumentBlock(doc = {}) {
        const uid = uniqueId('doc');
        const $blk = $(`
            <div class="document-block mb-3" id="${uid}" data-existing-url="${doc.url || ''}">
                <div class="row gx-2">
                    <div class="col-md-4"><label>Libellé</label><input type="text" class="form-control doc-libelle" value="${doc.libelle || ''}"></div>
                    <div class="col-md-3"><label>Date</label><input type="date" class="form-control doc-date" value="${doc.dateDossier || ''}"></div>
                    <div class="col-md-3 text-end d-flex align-items-end justify-content-end">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-document"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="row mt-2 gx-2">
                    <div class="col-md-6"><label>Description</label><textarea class="form-control doc-description" rows="3">${doc.description || ''}</textarea></div>
                    <div class="col-md-6">
                        <label>Fichier</label>
                        ${doc.url ? `<p><a href="/${doc.url}" target="_blank" download>Télécharger</a></p>` : ''}
                        <input type="file" class="doc-fichier" name="documentsFiles[]">
                    </div>
                </div>
            </div>
        `);
        $('#documentsContainer').append($blk);
    }
    $('#documentsContainer').on('click', '.btn-remove-document', function() {
        $(this).closest('.document-block').remove();
        markDirty('traitements');
    });

    // Devis — Services
    $('#btnAddService').on('click', () => {
        const blk = createServiceBlock();
        $('#servicesContainer').append(blk);
        updateDevisTotal();
    });
    function createServiceBlock(data = {}) {
        const uid = uniqueId('service');
        const qte = data.qte || 1, montant = data.montant || 0;
        const $blk = $(`
            <div class="service-block border rounded p-3 mb-3" id="${uid}">
                <div class="row gx-2 align-items-end">
                    <div class="col-md-5"><label>Désignation</label><input type="text" class="form-control service-designation" value="${data.designation || ''}" required></div>
                    <div class="col-md-2"><label>Quantité</label><input type="number" class="form-control service-qte" value="${qte}" required></div>
                    <div class="col-md-3"><label>Prix unitaire</label><input type="number" class="form-control service-montant" step="0.01" value="${montant}" required></div>
                    <div class="col-md-1"><label>Total</label><input type="text" class="form-control service-total" value="${(qte * montant).toFixed(2)}" readonly></div>
                    <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-service"><i class="fas fa-trash"></i></button></div>
                </div>
            </div>
        `);
        $blk.on('input', '.service-qte, .service-montant', function() {
            const $p = $(this).closest('.service-block');
            const qt = parseFloat($p.find('.service-qte').val()) || 0;
            const pr = parseFloat($p.find('.service-montant').val()) || 0;
            $p.find('.service-total').val((qt * pr).toFixed(2));
            updateDevisTotal();
            markDirty('devis');
        });
        $blk.on('click', '.btn-remove-service', function() {
            $(this).closest('.service-block').remove();
            updateDevisTotal();
            markDirty('devis');
        });
        return $blk;
    }
    function updateDevisTotal() {
        let tot = 0;
        $('#servicesContainer .service-total').each(function() {
            tot += parseFloat($(this).val()) || 0;
        });
        $('#devisTotal').val(tot.toFixed(2));
    }

    // Actes médicaux
    $('#btnAddActe').on('click', () => {
        const blk = createActeBlock();
        $('#actesContainer').append(blk);
        markDirty('consult');
    });
    function createActeBlock(a = {}) {
        const uid = uniqueId('acte');
        const $blk = $(`
            <div class="acte-block mb-3 border" id="${uid}">
                <div class="row gx-2">
                    <div class="col-md-8">
                        <div class="row gx-2">
                            <div class="col-md-6 mb-2"><label>Dent</label><input type="text" class="form-control acte-dent" value="${a.dent || ''}"></div>
                            <div class="col-md-6 mb-2">
                                <label for="acte-type">Type</label>
                                <select class="form-control acte-type" id="acte-type">
                                    <option value="">Sélectionnez un type d'acte</option>
                                    <option value="Consultation" ${a.type === 'Consultation' ? 'selected' : ''}>Consultation</option>
                                    <option value="Détartrage" ${a.type === 'Détartrage' ? 'selected' : ''}>Détartrage</option>
                                    <option value="Extraction" ${a.type === 'Extraction' ? 'selected' : ''}>Extraction</option>
                                    <option value="Composite" ${a.type === 'Composite' ? 'selected' : ''}>Composite</option>
                                    <option value="Amalgame" ${a.type === 'Amalgame' ? 'selected' : ''}>Amalgame</option>
                                    <option value="Traitement de canal" ${a.type === 'Traitement de canal' ? 'selected' : ''}>Traitement de canal</option>
                                    <option value="Traumatisme" ${a.type === 'Traumatisme' ? 'selected' : ''}>Traumatisme</option>
                                    <option value="Couronne" ${a.type === 'Couronne' ? 'selected' : ''}>Couronne</option>
                                    <option value="Blanchiment" ${a.type === 'Blanchiment' ? 'selected' : ''}>Blanchiment</option>
                                    <option value="Radio" ${a.type === 'Radio' ? 'selected' : ''}>Radio</option>
                                    <option value="Prothèse" ${a.type === 'Prothèse' ? 'selected' : ''}>Prothèse</option>
                                    <option value="Orthodontie" ${a.type === 'Orthodontie' ? 'selected' : ''}>Orthodontie</option>
                                    <option value="Chirurgie" ${a.type === 'Chirurgie' ? 'selected' : ''}>Chirurgie</option>
                                    
                                </select>
                            </div>
                            <div class="col-md-6"><label>Prix</label><input type="number" step="0.01" class="form-control acte-prix" value="${a.prix || ''}"></div>
                            <div class="col-md-6"><label>Quantité</label><input type="number" class="form-control acte-qte" value="${a.quantite || 1}"></div>
                        </div>
                    </div>
                    <div class="col-md-3"><label>Description</label><textarea class="form-control acte-desc" rows="4">${a.description || ''}</textarea></div>
                    <div class="col-md-1 text-end d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-acte"><i class="fas fa-trash"></i></button></div>
                </div>
            </div>
        `);
        $blk.on('input', '.form-control', function() {
            markDirty('consult');
        });
        $blk.on('click', '.btn-remove-acte', function() {
            $(this).closest('.acte-block').remove();
            markDirty('consult');
        });
        return $blk;
    }
});
