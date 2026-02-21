$(function () {
  const patientId = parseInt(window.location.pathname.split('/')[3], 10);
  let dossierData = {};

  function loadData() {
    $.get(`/api/patient/${patientId}/dossier`, function (data) {
      dossierData = data;
      renderAll();
       
    });
  }

  function renderAll() {
    renderPatientInfo(); 
    // 2. Liaison sur les boutons “Ajouter”
$('#btn-add-antecedent').on('click', () => addEditableCard('antecedents'));
$('#btn-add-allergy').  on('click', () => addEditableCard('allergies'));
    renderFiches(); 
    renderRdvs();
  }

function renderPatientInfo() {
    const container = $('#patient-info-cards').empty();
    const patient = dossierData; // Assuming the data is directly the patient object
    $('#allergies-section').remove();
  $('#antecedents-section').remove();
  $('#action-section').remove();


    const fields = [
        { key: 'nom', label: 'Nom', type: 'text' },
        { key: 'prenom', label: 'Prénom', type: 'text' },
        { key: 'dateNaissance', label: 'Date de naissance', type: 'date' }, 
        { key: 'sexe', label: 'Sexe', type: 'select', options: ['Homme', 'Femme', 'Autre'] },
        { key: 'telephone', label: 'Téléphone', type: 'text' },
        { key: 'adresse', label: 'Adresse', type: 'text' },
        { key: 'numCarnet', label: 'Numéro de carnet', type: 'text' },
        { key: 'groupeSanguin', label: 'Groupe sanguin', type: 'text' },
        { key: 'dateInscription', label: 'Date d\'inscription', type: 'datetime-local', readonly: true },
        { key: 'contactUrgence.nom', label: 'Nom du contact d\'urgence', type: 'text' },
        { key: 'contactUrgence.lienParente', label: 'Lien de parenté', type: 'text' },
        { key: 'contactUrgence.telephone', label: 'Téléphone du contact d\'urgence', type: 'text' }
    ];

    fields.forEach(f => {
        const card = $('<div>').addClass('card mb-3');
        const body = $('<div>').addClass('card-body');

        $('<label>')
            .addClass('form-label')
            .text(f.label)
            .attr('for', `field-${f.key}`)
            .appendTo(body);

        let input;

        if (f.type === 'select') {
            input = $('<select>')
                .addClass('form-select form-control')
                .attr('id', `field-${f.key}`)
                .data('field', f.key);

            f.options.forEach(opt => {
                $('<option>')
                    .val(opt)
                    .text(opt)
                    .prop('selected', patient[f.key] === opt)
                    .appendTo(input);
            });
        } else {
            input = $('<input>')
                .addClass('form-control')
                .attr('type', f.type)
                .attr('id', `field-${f.key}`)
                .data('field', f.key);

            // Handle nested keys for emergency contact
            if (f.key.includes('.')) {
                const keys = f.key.split('.');
                const value = keys.reduce((obj, key) => (obj && obj[key] !== undefined ? obj[key] : ''), patient);
                input.val(value || '');
            } else {
                input.val(patient[f.key] || '');
            }

            if (f.readonly) {
                input.prop('readonly', true);
            }
        }

        // Special formatting for dates
        if (f.key === 'dateNaissance' && patient.dateNaissance) {
            input.val(patient.dateNaissance.split('T')[0]); // Format YYYY-MM-DD
        } else if (f.key === 'dateInscription' && patient.dateInscription) {
            const dt = new Date(patient.dateInscription);
            const localDateTime = dt.toISOString().slice(0, 16);
            input.val(localDateTime);
        }

        input.appendTo(body);
        card.append(body);
        container.append(card);
    });


    const allergiesCard = $('<div>').addClass('card mb-4');
    const allergiesHeader = $('<div>').addClass('card-header py-3 d-flex align-items-center justify-content-between');
    const allergiesTitle = $('<h6>').addClass('m-0 font-weight-bold text-primary').text('Allergies');
    
    const addAllergyButton = $('<button>')
        .addClass('btn btn-primary')
        .attr('id', 'btn-add-allergy')
        .attr('data-toggle', 'modal')
        .attr('data-target', '#addAllergyModal')
        .html('<i class="fas fa-plus me-2"></i>Ajouter une allergie');

    allergiesHeader.append(allergiesTitle).append(addAllergyButton);
    
    const allergiesBody = $('<div>').addClass('card-body');
    const allergiesContent = $('<div>').addClass('row mb-4 mx-2').attr('id', 'allergies-cards');
    
    // Affichage des allergies existantes (version éditable)
    if (patient.allergies && patient.allergies.length > 0) {
        patient.allergies.forEach((allergy, index) => {
            const allergyCard = $('<div>').addClass('col-md-6 mb-3');
            const card = $('<div>').addClass('card h-100').data('id', allergy.id);
            const cardBody = $('<div>').addClass('card-body');
            
            // Champs éditables
            $('<input>')
                .addClass('form-control mb-2')
                .attr('placeholder', 'Libellé')
                .val(allergy.libelle || '')
                .appendTo(cardBody);
                
            $('<textarea>')
                .addClass('form-control mb-2')
                .attr('placeholder', 'Description')
                .val(allergy.description || '')
                .appendTo(cardBody);
             
            
            // Bouton de suppression
            const removeBtn = $('<button>')
            .addClass('btn btn-sm btn-outline-danger')
            .text('Supprimer')
            .on('click', () => {
            allergyCard.remove(); 
            });
            
            cardBody.append(removeBtn);
            card.append(cardBody);
            allergyCard.append(card);
            allergiesContent.append(allergyCard);
        });
    } else {
        allergiesContent.append($('<p>').text('Aucune allergie enregistrée').addClass('text-muted'));
    }
    
    allergiesBody.append(allergiesContent);
    allergiesCard.append(allergiesHeader).append(allergiesBody);
    container.parent().append(allergiesCard);

    // Section Antécédents
    const antecedentsCard = $('<div>').addClass('card mb-4');
    const antecedentsHeader = $('<div>').addClass('card-header py-3 d-flex align-items-center justify-content-between');
    const antecedentsTitle = $('<h6>').addClass('m-0 font-weight-bold text-primary').text('Antécédents médicaux');
    
    const addAntecedentButton = $('<button>')
        .addClass('btn btn-primary')
        .attr('id', 'btn-add-antecedent')
        .attr('data-toggle', 'modal')
        .attr('data-target', '#addAntecedentModal')
        .html('<i class="fas fa-plus me-2"></i>Ajouter un antécédent');

    antecedentsHeader.append(antecedentsTitle).append(addAntecedentButton);
    
    const antecedentsBody = $('<div>').addClass('card-body');
    const antecedentsContent = $('<div>').addClass('row mb-4 mx-2').attr('id', 'antecedents-cards');
    
    // Affichage des antécédents existants (version éditable)
    if (patient.antecedents && patient.antecedents.length > 0) {
        patient.antecedents.forEach((antecedent, index) => {
            const antecedentCard = $('<div>').addClass('col-md-6 mb-3');
            const card = $('<div>').addClass('card h-100').data('id', antecedent.id);
            const cardBody = $('<div>').addClass('card-body');
            
            // Champs éditables
            $('<input>')
                .addClass('form-control mb-2')
                .attr('placeholder', 'Type')
                .val(antecedent.type || '')
                .appendTo(cardBody);
                
            $('<textarea>')
                .addClass('form-control mb-2')
                .attr('placeholder', 'Description')
                .val(antecedent.description || '')
                .appendTo(cardBody);
            
            
             // Bouton de suppression
            const removeBtn = $('<button>')
            .addClass('btn btn-sm btn-outline-danger')
            .text('Supprimer')
            .on('click', () => {
            antecedentCard.remove(); 
            });
            
            cardBody.append(removeBtn);
            card.append(cardBody);
            antecedentCard.append(card);
            antecedentsContent.append(antecedentCard);
        });
    } else {
        antecedentsContent.append($('<p>').text('Aucun antécédent enregistré').addClass('text-muted'));
    }
    
    antecedentsBody.append(antecedentsContent);
    antecedentsCard.append(antecedentsHeader).append(antecedentsBody);
    container.parent().append(antecedentsCard);

    
    // Adding action buttons
    const actionCard = $('<div>').addClass('card mb-3');
    const actionBody = $('<div>').addClass('card-body d-flex justify-content-between');

    $('<button>')
        .addClass('btn btn-primary')
        .text('Enregistrer les modifications')
        .click(savePatientInfo)
        .appendTo(actionBody);

    $('<button>')
        .addClass('btn btn-outline-secondary')
        .text('Annuler')
        .click(loadData)
        .appendTo(actionBody);

    actionCard.append(actionBody);
    container.parent().append(actionCard);
}

function getFormData() {
  // 1. Champs “simples”
  const nom             = $('#field-nom').val().trim();
  const prenom          = $('#field-prenom').val().trim();
  const dateNaissance   = $('#field-dateNaissance').val();      // format YYYY-MM-DD
  const sexe            = $('#field-sexe').val();
  const telephone       = $('#field-telephone').val().trim();
  const adresse         = $('#field-adresse').val().trim();
  const numCarnet       = $('#field-numCarnet').val().trim();
  const groupeSanguin   = $('#field-groupeSanguin').val().trim();
  const dateInscription = $('#field-dateInscription').val();    // datetime-local

  // 2. Contact d’urgence (ManyToOne) 
  const contactUrgenceNom      = $('#field-contactUrgence\\.nom').val().trim();
  const contactUrgenceLien     = $('#field-contactUrgence\\.lienParente').val().trim();
  const contactUrgenceTele     = $('#field-contactUrgence\\.telephone').val().trim();

  // 3. Allergies
  const allergies = [];
  $('#allergies-cards .card').each(function() {
    allergies.push({
      id:          $(this).data('id') || null,
      libelle:     $(this).find('input[placeholder="Libellé"]').val().trim(),
      description: $(this).find('textarea[placeholder="Description"]').val().trim()
    });
  });

  // 4. Antécédents
  const antecedents = [];
  $('#antecedents-cards .card').each(function() {
    antecedents.push({
      id:          $(this).data('id') || null,
      type:        $(this).find('input[placeholder="Type"]').val().trim(),
      description: $(this).find('textarea[placeholder="Description"]').val().trim()
    });
  });

  // 5. Construction du payload
  return {
    nom,
    prenom,
    dateNaissance,
    sexe,
    telephone,
    adresse,
    numCarnet,
    groupeSanguin,
    dateInscription,
    contactUrgence: { 
      nom:   contactUrgenceNom,
      lienParente: contactUrgenceLien,
      telephone:   contactUrgenceTele
    },
    allergies,
    antecedents
  };
}
    

function savePatientInfo() {
  const payload = getFormData();

  $.ajax({
    url: `/api/patient/${patientId}/dossier/update`,
    method: 'PUT',
    contentType: 'application/json',
    data: JSON.stringify(payload),
   success: function() {
      showToastModal({
                      message: "Dossier mis à jour !",
                      type: "success"
                    });
      loadData();
    },
    error: function(xhr) {
      showToastModal({
                      message: "Erreur lors de la mise à jour",
                      type: "error",
                      duration: 3000
                    });
    }
  });
}



 function addEditableCard(section) {
  const container = $(`#${section}-cards`);

  // 1) Si le message “Aucune … enregistrée” est présent, on le supprime
  container.find('p.text-muted').remove();

  // 2) On construit ensuite la nouvelle carte vierge
  const col = $('<div>').addClass('col-md-6 mb-3');
  const card = $('<div>').addClass('card h-100');
  const body = $('<div>').addClass('card-body');

  if (section === 'allergies') {
    body.append(`<input class="form-control mb-2" placeholder="Libellé" value=""/>`);
    body.append(`<textarea class="form-control mb-2" placeholder="Description"></textarea>`);
  } else if (section === 'antecedents') {
    body.append(`<input class="form-control mb-2" placeholder="Type" value=""/>`);
    body.append(`<textarea class="form-control mb-2" placeholder="Description"></textarea>`);
  }

  const removeBtn = $('<button>')
    .addClass('btn btn-sm btn-outline-danger')
    .text('Supprimer')
    .on('click', () => {
      col.remove(); 
    });

  body.append(removeBtn);
  card.append(body);
  col.append(card);
  container.append(col);
 
}
 
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (isNaN(date)) return dateString;
    // Retourne sous forme "YYYY-MM-DD HH:mm"
    const pad = n => n.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function getStatusBadge(status) {
    const statusMap = {
        '-2': { text: 'Annulé', class: 'badge-danger' },
        '-1': { text: 'Reporté', class: 'badge-warning' },
        '0': { text: 'En attente', class: 'badge-secondary' },
        '1': { text: 'Validé', class: 'badge-success' }
    };
    const statusInfo = statusMap[status] || { text: 'Inconnu', class: 'badge-dark' };
    return `<span class="badge ${statusInfo.class}">${statusInfo.text}</span>`;
}

function renderRdvs() {
    const table = $('#rdvs-table').DataTable({
        data: dossierData.rdvs || [],
        columns: [
            { data: 'dateCreation', title: 'Date de création', render: function(data) { return formatDate(data); }, orderable: true },
            { data: 'dateRdv', title: 'Date de rdv', render: function(data) { return formatDate(data); }, orderable: true },
            { data: 'medecinNom', title: 'Médecin', orderable: false },
            { data: 'statut', title: 'Statut', render: function(data) { return getStatusBadge(data); }, orderable: false }
        ],
        order: [[0, 'desc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
        },
        columnDefs: [
            { targets: 0, width: '15%' }, // Date de création
            { targets: 1, width: '15%' }, // Date de rdv 
            { targets: 2, width: '40%' }, // Médecin
            { targets: 3, width: '15%' }  // Statut
        ],
    dom: 'Bfrtip',
        buttons: [
            {
                extend: 'pdfHtml5',
                text: 'Exporter en PDF',
                title: 'Liste des rendez-vous',
                className: 'mb-2',
                exportOptions: {
                    columns: [0, 1, 2, 3]
                },
                
            }
        ]
    });

    // $('#rdvs-table tbody').on('click', 'tr', function () {
    //     const data = table.row(this).data();
    //     window.location.href = `/rdv/${data.id}`;
    // });
}


  function renderFiches() {
    const $container = $('#fiches-medicales');
    dossierData.fiches.forEach(fiche => {
        // Create the main card for the consultation sheet
        // Calcul du montant total du devis
        let montantTotal = 0;
        if (fiche.devis && Array.isArray(fiche.devis.contenus)) {
            montantTotal = fiche.devis.contenus.reduce((sum, ligne) => {
          const qte = parseFloat(ligne.qte) || 1;
          const montant = parseFloat(ligne.montant) || 0;
          return sum + (qte * montant);
            }, 0);
        }

        const $ficheCard = $(`
            <div class="card shadow mb-3">
          <div class="card-header py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 font-weight-bold text-primary">
            Fiche de consultation - Référence #${fiche.id}
              </h6>
              <button type="button" class="btn btn-primary shadow-sm btnImpimerFiche" data-fiche-id="${fiche.id}">
            <i class="fas fa-print me-1"></i> Imprimer
              </button>
          </div>
            </div>
            <div class="custom-tabs">
          <ul class="nav nav-tabs" role="tablist">
              <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#motif-soins-${fiche.id}" role="tab">Motif & Histoire</a>
              </li>
              <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#examens-${fiche.id}" role="tab">Examens</a>
              </li>
              <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#traitements-documents-${fiche.id}" role="tab">Traitements & Documents</a>
              </li>
              <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#devis-${fiche.id}" role="tab">Devis</a>
              </li>
              <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#seances-passees-${fiche.id}" role="tab">Séances Passées</a>
              </li>
          </ul>
          <div class="tab-content">

              <div class="tab-pane fade show active" id="motif-soins-${fiche.id}" role="tabpanel">
            <div class="card shadow mb-3">
                <div class="card-body">
              <form id="motifSoinsForm">
                  <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="motif" class="form-label">Motif</label>
                    <textarea id="motif" class="form-control" name="motif" readonly>${fiche.motif || ''}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="histoireMaladie" class="form-label">Anamnese</label>
                    <textarea id="histoireMaladie" class="form-control" name="histoireMaladie" readonly>${fiche.histoireMaladie || ''}</textarea>
                </div>
                <div class="col-md-12 mb-3">
                    <label for="soinsAnterieurs" class="form-label">Soins antérieurs</label>
                    <textarea id="soinsAnterieurs" class="form-control" name="soinsAnterieurs" readonly>${fiche.soinsAnterieurs || ''}</textarea>
                </div>
                  </div>
              </form>
                </div>
                <div class="card-footer py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 font-weight-bold text-primary"></h6>
              <button type="button" class="btn btn-primary" id="btnSaveMotifSoins" style="display: none;">Sauvegarder</button>
                </div>
            </div>
              </div>
              <div class="tab-pane fade" id="examens-${fiche.id}" role="tabpanel">
            <div class="card shadow mb-3">
                <div class="card-body">
              <form id="examensForm">
                  <h6 class="mt-4 alert alert-primary">Examen Exobuccal</h6>
                  <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="exoInspection" class="form-label">Inspection</label>
                    <textarea id="exoInspection" class="form-control" name="exoInspection" readonly>${fiche.exoInspection || ''}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="exoPalpation" class="form-label">Palpation</label>
                    <textarea id="exoPalpation" class="form-control" name="exoPalpation" readonly>${fiche.exoPalpation || ''}</textarea>
                </div>
                  </div>
                  <h6 class="mt-4 alert alert-primary">Examen Endobuccal</h6>
                  <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="endoInspection" class="form-label">Inspection</label>
                    <textarea id="endoInspection" class="form-control" name="endoInspection" readonly>${fiche.endoInspection || ''}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="endoPalpation" class="form-label">Palpation</label>
                    <textarea id="endoPalpation" class="form-control" name="endoPalpation" readonly>${fiche.endoPalpation || ''}</textarea>
                </div>
                  </div>
                  <div class="mb-3">
                <label for="occlusion" class="form-label">Occlusion</label>
                <textarea id="occlusion" class="form-control" name="occlusion" readonly>${fiche.occlusion || ''}</textarea>
                  </div>
                  <div class="mb-3">
                <label for="examenParodontal" class="form-label">Examen parodontal</label>
                <textarea id="examenParodontal" class="form-control" name="examenParodontal" readonly>${fiche.examenParodontal || ''}</textarea>
                  </div>
                  <div class="mb-3">
                <label for="diagnostic" class="form-label">Diagnostic</label>
                <textarea id="diagnostic" class="form-control" name="diagnostic" readonly>${fiche.diagnostic || ''}</textarea>
                  </div>
                  <div class="card mb-3">
                <div class="card-header d-flex align-items-center justify-content-between alert alert-primary">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-tooth me-1"></i> Examens Dentaires</h6>
                </div>
                <div class="card-body">
                    <div id="toothContainer">
                  <h5>Arcade supérieure</h5>
                  <div class="row">
                      ${[ [11, 21], [12, 22], [13, 23], [14, 24], [15, 25], [16, 26], [17, 27], [18, 28] ].map(pair => `
                    <div class="col-md-6 mb-2">
                        <div class="input-group">
                      <span class="input-group-text">${pair[0]}</span>
                      <textarea class="form-control tooth-input" id="tooth-${pair[0]}" data-tooth="${pair[0]}" name="toothsCheck[${pair[0]}]" readonly></textarea>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="input-group">
                      <span class="input-group-text">${pair[1]}</span>
                      <textarea class="form-control tooth-input" id="tooth-${pair[1]}" data-tooth="${pair[1]}" name="toothsCheck[${pair[1]}]" readonly></textarea>
                        </div>
                    </div>
                      `).join('')}
                  </div>
                  <h5>Arcade inférieure</h5>
                  <div class="row">
                      ${[ [31, 41], [32, 42], [33, 43], [34, 44], [35, 45], [36, 46], [37, 47], [38, 48] ].map(pair => `
                    <div class="col-md-6 mb-2">
                        <div class="input-group">
                      <span class="input-group-text">${pair[0]}</span>
                      <textarea class="form-control tooth-input" id="tooth-${pair[0]}" data-tooth="${pair[0]}" name="toothsCheck[${pair[0]}]" readonly></textarea>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="input-group">
                      <span class="input-group-text">${pair[1]}</span>
                      <textarea class="form-control tooth-input" id="tooth-${pair[1]}" data-tooth="${pair[1]}" name="toothsCheck[${pair[1]}]" readonly></textarea>
                        </div>
                    </div>
                      `).join('')}
                  </div>
                    </div>
                </div>
                  </div>
              </form>
                </div>
                <div class="card-footer py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 font-weight-bold text-primary"></h6>
              <button type="button" class="btn btn-primary" id="btnSaveExamens" style="display: none;">Sauvegarder</button>
                </div>
            </div>
              </div>
              <div class="tab-pane fade" id="traitements-documents-${fiche.id}" role="tabpanel">
            <div class="card shadow mb-3">
                <div class="card-body">
              <form id="traitementsDocumentsForm">
                  <h6 class="mt-4 alert alert-primary"><i class="fas fa-syringe me-1"></i> Traitements</h6>
                  <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="traitementUrgence" class="form-label">Urgence</label>
                    <textarea id="traitementUrgence" class="form-control" name="traitementUrgence" readonly>${fiche.traitementUrgence || ''}</textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="traitementDentaire" class="form-label">Dentaire</label>
                    <textarea id="traitementDentaire" class="form-control" name="traitementDentaire" readonly>${fiche.traitementDentaire || ''}</textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="traitementParodontal" class="form-label">Parodontal</label>
                    <textarea id="traitementParodontal" class="form-control" name="traitementParodontal" readonly>${fiche.traitementParodontal || ''}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="traitementOrthodontique" class="form-label">Orthodontique</label>
                    <textarea id="traitementOrthodontique" class="form-control" name="traitementOrthodontique" readonly>${fiche.traitementOrthodontique || ''}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="autres" class="form-label">Autres</label>
                    <textarea id="autres" class="form-control" name="autres" readonly>${fiche.autres || ''}</textarea>
                </div>
                  </div>

                  <div class="card mb-3">
                    <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                  <i class="fas fa-file-medical me-2"></i> Documents médicaux
                </h6>
                
                    </div>
                    <div class="card-body">
                <div id="documentsContainer">
        ${fiche.documents && fiche.documents.length ? fiche.documents.map((doc, i) => `
          <div class="document-block mb-3" id="doc-${i}" data-existing-url="${doc.url || ''}">
            <div class="row gx-2">
        <div class="col-md-4">
          <label>Libellé</label>
          <input type="text" class="form-control doc-libelle" value="${doc.libelle || ''}">
        </div>
        <div class="col-md-3">
          <label>Date</label>
          <input type="date" class="form-control doc-date" value="${doc.dateDossier || ''}">
        </div>
        <div class="col-md-3 text-end d-flex align-items-end justify-content-end"> 
        </div>
            </div>
            <div class="row mt-2 gx-2">
        <div class="col-md-6">
          <label>Description</label>
          <textarea class="form-control doc-description" rows="3">${doc.description || ''}</textarea>
        </div>
        <div class="col-md-6">
          <label>Fichier</label>
          ${ doc.url ? `<p><a href="/${doc.url}" target="_blank" download>Télécharger</a></p>` : ''}
          // <input type="file" class="doc-fichier" name="documentsFiles[]">
        </div>
            </div>
          </div>
        `).join('') : ''}
      </div>

                    </div>
                  </div>
                  
              </form>
                </div>
                <div class="card-footer py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 font-weight-bold text-primary"></h6>
              <button type="button" class="btn btn-primary" id="btnSaveTraitementsDocuments" style="display: none;">Sauvegarder</button>
                </div>
            </div>
              </div>
              <div class="tab-pane fade" id="devis-${fiche.id}" role="tabpanel">
            <div class="card shadow mb-3">
                <div class="card-body">
              <form id="devisForm">
        <div class="mb-3 row">
          <label for="devisDate" class="col-sm-3 col-form-label">Date</label>
          <div class="col-sm-9">
            <input type="date" class="form-control" name="date" id="devisDate" value="${fiche.devis ? fiche.devis.date : ''}" readonly required>
          </div>
        </div>

        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Services</h6> 
        </div>

        <div id="servicesContainer">
          ${fiche.devis && fiche.devis.contenus && fiche.devis.contenus.length ? fiche.devis.contenus.map((ligne, i) => `
            <div class="service-block mb-3" id="service-${i}">
        <div class="row gx-2 align-items-end">
          <div class="col-md-5">
            <label>Libellé</label>
            <input type="text" class="form-control service-libelle" value="${ligne.designation || ''}" required>
          </div>
          <div class="col-md-3">
            <label>Prix unitaire</label>
            <input type="number" class="form-control service-prix" value="${ligne.montant || 0}" step="0.01" required>
          </div>
          <div class="col-md-2">
            <label>Quantité</label>
            <input type="number" class="form-control service-quantite" value="${ligne.qte || 1}" min="1" required>
          </div>
          <div class="col-md-2 text-end">
            <button type="button" class="btn btn-outline-danger btn-remove-service">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
            </div>
          `).join('') : ''}
        </div>

        <hr>
        <div class="mb-3 row">
          <label class="col-sm-3 col-form-label fw-bold">Montant total à Payer</label>
          <div class="col-sm-9">
            <input type="text" readonly class="form-control-plaintext fw-bold fs-5" id="devisTotal" value="${montantTotal.toFixed(2)}">
          </div>
        </div>
      </form>

                </div>
                <div class="card-footer py-3 d-flex justify-content-between align-items-center">
              <h6 class="m-0 font-weight-bold text-primary"></h6>
              <button type="button" class="btn btn-primary" id="btnSaveDevis" style="display: none;">Sauvegarder</button>
                </div>
            </div>
              </div>
              <div class="tab-pane fade" id="seances-passees-${fiche.id}" role="tabpanel">
            <div class="card shadow mb-3">
                <div class="card-body">
              <div id="seancesContainer">
          ${fiche.consultations.length > 0 ? fiche.consultations.map((seance, index) => `
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-light" id="heading-${index}">
          <h6 class="mb-0 d-flex justify-content-between align-items-center">
              <span>
            <i class="fas fa-calendar-alt me-2"></i>
            Séance du ${new Date(seance.date).toLocaleDateString('fr-FR')}
              </span>
              <button class="btn btn-link" data-toggle="collapse" data-target="#collapse-${index}"
                aria-expanded="false" aria-controls="collapse-${index}">
            Voir les actes
              </button>
          </h6>
            </div>
            <div id="collapse-${index}" class="collapse" aria-labelledby="heading-${index}" data-parent="#seancesContainer">
          <div class="card-body">
              <p class="mb-1">
            <strong>Médecin :</strong> ${seance.medecin || '—'}<br>
            <strong>Infirmier :</strong> ${seance.infirmier || '—'}<br>
            <strong>Salle :</strong> ${seance.salle || '—'}
              </p>
              <p class="small text-muted mb-0">
            <strong>Note :</strong> ${seance.noteSeance || '—'}
              </p>
              <!-- Section pour les actes posés -->
              <div class="mt-2">
            <strong>Soins Médicaux :</strong>
            <ul class="list-group">
                ${seance.actes && seance.actes.length > 0 ? seance.actes.map(acte => `
              <li class="list-group-item">${acte.description || '—'}</li>
                `).join('') : '<li class="list-group-item">Aucun acte enregistré.</li>'}
            </ul>
              </div>
          </div>
            </div>
        </div>
          `).join('') : '<div class="col-12"><p class="text-muted fst-italic alert alert-secondary">Aucune séance clôturée pour cette fiche.</p></div>'}
      </div>
                </div>
            </div>
              </div>
          </div>
            </div>
        `);  
        $container.append($ficheCard);
    });
}



  loadData();

  // — Collecteurs de données
    function collectMotifData() {
      return {
        motif:            $('#motif').val(),
        histoireMaladie:  $('#histoireMaladie').val(),
        soinsAnterieurs:  $('#soinsAnterieurs').val()
      };
    }
  
    function collectExamensData() {
      const examensDentaires = {};
      $('#toothContainer .tooth-input').each(function() {
        examensDentaires[$(this).data('tooth')] = $(this).val();
      });
      return Object.assign({}, {
        exoInspection:      $('#exoInspection').val(),
        exoPalpation:       $('#exoPalpation').val(),
        endoInspection:     $('#endoInspection').val(),
        endoPalpation:      $('#endoPalpation').val(),
        occlusion:          $('#occlusion').val(),
        examenParodontal:   $('#examenParodontal').val(),
        diagnostic:         $('#diagnostic').val()
      }, { examensDentaires });
    }
  
    function collectTraitementsData() {
      const data = {
        traitementUrgence:        $('#traitementUrgence').val(),
        traitementDentaire:       $('#traitementDentaire').val(),
        traitementParodontal:     $('#traitementParodontal').val(),
        traitementOrthodontique:  $('#traitementOrthodontique').val(),
        autres:                   $('#autres').val(),
        documents: []
      };
      const formData = new FormData();
      formData.append('data', JSON.stringify(data));
  
      $('#documentsContainer .document-block').each(function(i) {
        const $blk = $(this);
        data.documents.push({
          libelle:     $blk.find('.doc-libelle').val(),
          dateDossier: $blk.find('.doc-date').val(),
          description: $blk.find('.doc-description').val(),
          url:         $blk.data('existing-url') || null
        });
        const file = $blk.find('input[type="file"]')[0]?.files[0];
        if (file) {
          formData.append(`documentsFiles[${i}]`, file);
        }
      });
      // Réécrire data dans formData
      formData.set('data', JSON.stringify(data));
      return formData;
    }
  
    function collectDevisData() {
      const contenus = [];
      $('#servicesContainer .service-block').each(function() {
        const $b = $(this);
        contenus.push({
          designation: $b.find('.service-designation').val(),
          qte:         parseInt($b.find('.service-qte').val())     || 1,
          montant:     parseFloat($b.find('.service-montant').val()) || 0
        });
      });
      return {
        date:     $('#devisDate').val(),
        contenus: contenus
      };
    }
  
    function collectConsultData() {
      const actes = [];
      $('#actesContainer .acte-block').each(function() {
        const $b = $(this);
        actes.push({
          dent:        $b.find('.acte-dent').val(),
          type:        $b.find('.acte-type').val(),
          description: $b.find('.acte-desc').val(),
          prix:        parseFloat($b.find('.acte-prix').val()) || 0,
          quantite:    parseInt($b.find('.acte-qte').val())    || 1
        });
      });
      return {
        medecinId:   $('#medecin').val(),
        infirmierId: $('#infirmier').val(),
        salleId:     $('#salle').val(),
        noteSeance:  $('#noteSeance').val(),
        actes:       actes
      };
    }
  
    // — Envois AJAX vers les nouvelles routes
    function sendMotifUpdate() {
      $.ajax({
        url: `/api/fiche/${ficheId}/consultation/${consultId}/update-motif`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(collectMotifData()),
        success() {
          isMotifModified = false;
          savedSections.motif = true;
          updateGlobalSaveStatus();
          showToastModal({ message: 'Motif & histoire enregistrés', type: 'success', duration: 3000 });
        },
        error() {
          showToastModal({ message: 'Erreur sauvegarde motif', type: 'error', duration: 3000 });
        }
      });
    }
  
    function sendExamensUpdate() {
      $.ajax({
        url: `/api/fiche/${ficheId}/consultation/${consultId}/update-examens`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(collectExamensData()),
        success() {
          isExamensModified = false;
          savedSections.examens = true;
          updateGlobalSaveStatus();
          showToastModal({ message: 'Examens enregistrés', type: 'success', duration: 3000 });
        },
        error() {
          showToastModal({ message: 'Erreur sauvegarde examens', type: 'error', duration: 3000 });
        }
      });
    }
  
    function sendTraitementsUpdate() {
      $.ajax({
        url: `/api/fiche/${ficheId}/consultation/${consultId}/update-traitements`,
        method: 'POST',
        processData: false,
        contentType: false,
        data: collectTraitementsData(),
        success() {
          isTraitementsModified = false;
          savedSections.traitements = true;
          updateGlobalSaveStatus();
          showToastModal({ message: 'Traitements et documents enregistrés', type: 'success', duration: 3000 });
        },
        error() {
          showToastModal({ message: 'Erreur sauvegarde traitements', type: 'error', duration: 3000 });
        }
      });
    }
  
    function sendDevisUpdate() {
      $.ajax({
        url: `/api/fiche/${ficheId}/consultation/${consultId}/update-devis`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(collectDevisData()),
        success() {
          isDevisModified = false;
          savedSections.devis = true;
          updateGlobalSaveStatus();
          showToastModal({ message: 'Devis enregistré', type: 'success', duration: 3000 });
        },
        error() {
          showToastModal({ message: 'Erreur sauvegarde devis', type: 'error', duration: 3000 });
        }
      });
    }
  
    function sendConsultUpdate() {
      $.ajax({
        url: `/api/fiche/${ficheId}/consultation/${consultId}/update`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(collectConsultData()),
        success() {
          isConsultModified = false;
          savedSections.consult = true;
          updateGlobalSaveStatus();
          showToastModal({ message: 'Consultation enregistrée', type: 'success', duration: 3000 });
        },
        error() {
          showToastModal({ message: 'Erreur sauvegarde consultation', type: 'error', duration: 3000 });
        }
      });
    }

    // — Fonctions utilitaires pour blocs dynamiques
    function uniqueId(prefix = 'id') {
      return `${prefix}_${Date.now()}_${Math.floor(Math.random()*1000)}`;
    }
  
    // Devis — Services
    $('#btnAddService').on('click', () => {
      const blk = createServiceBlock();
      $('#servicesContainer').append(blk);
      updateDevisTotal();
    });
    function createServiceBlock(data={}) {
      const uid = uniqueId('service');
      const qte = data.qte||1, montant = data.montant||0;
      const $blk = $(`
        <div class="service-block border rounded p-3 mb-3" id="${uid}">
          <div class="row gx-2 align-items-end">
            <div class="col-md-5"><label>Désignation</label><input type="text" class="form-control service-designation" value="${data.designation||''}" required></div>
            <div class="col-md-2"><label>Quantité</label><input type="number" class="form-control service-qte" value="${qte}" required></div>
            <div class="col-md-3"><label>Prix unitaire</label><input type="number" class="form-control service-montant" step="0.01" value="${montant}" required></div>
            <div class="col-md-1"><label>Total</label><input type="text" class="form-control service-total" value="${(qte*montant).toFixed(2)}" readonly></div>
            <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-service"><i class="fas fa-trash"></i></button></div>
          </div>
        </div>
      `);
      $blk.on('input','.service-qte, .service-montant', function(){
        const $p = $(this).closest('.service-block');
        const qt = parseFloat($p.find('.service-qte').val())||0;
        const pr = parseFloat($p.find('.service-montant').val())||0;
        $p.find('.service-total').val((qt*pr).toFixed(2));
        updateDevisTotal();
        isDevisModified = true; updateGlobalSaveStatus();
      });
      $blk.on('click','.btn-remove-service', function(){
        $(this).closest('.service-block').remove();
        updateDevisTotal();
        isDevisModified = true; updateGlobalSaveStatus();
      });
      return $blk;
    }
    function updateDevisTotal(){
      let tot = 0;
      $('#servicesContainer .service-total').each(function(){
        tot += parseFloat($(this).val())||0;
      });
      $('#devisTotal').val(tot.toFixed(2));
    }

    $('#btnSaveMotifSoins').on('click', sendMotifUpdate);
    $('#btnSaveExamens').on('click', sendExamensUpdate);
    $('#btnSaveTraitementsDocuments').on('click', sendTraitementsUpdate);
    $('#btnSaveDevis').on('click', sendDevisUpdate);

    $('#btnImpimerInfosPerso').on('click', () => {
        const url = `/api/patient/${dossierData.id}/dossier/print/infosperso`;
        window.open(url, '_blank');  // ouvre une nouvelle fenêtre et lance print()
    });

    // On suppose que patientId est déjà défini plus haut :
    $(document).on('click', '.btnImpimerFiche', function() {
    const ficheId = $(this).data('fiche-id');
    const url = `/api/patient/${patientId}/fiche/${ficheId}/print`;
    window.open(url, '_blank');
    });

});
