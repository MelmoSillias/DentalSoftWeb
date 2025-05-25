$(function() {
    // IDs injectés par Twig
    const ficheId    = window.ficheId;
    const consultId  = window.consultId;
    
  
    // — Flags de modification par section
    let isMotifModified       = false;
    let isExamensModified     = false;
    let isTraitementsModified = false;
    let isDevisModified       = false;
    let isConsultModified     = false;
  
    // — Suivi des sections sauvegardées
    const savedSections = {
      motif:       false,
      examens:     false,
      traitements: false,
      devis:       false,
      consult:     false
    };
    const totalSections = Object.keys(savedSections).length;
  
    // — Met à jour l’indicateur global de statut
    function updateGlobalSaveStatus() {
      const $alert = $('#saveStatusIndicator .alert');
      $alert.removeClass('alert-secondary alert-warning alert-info alert-success');
  
      let text, cls;
      if (isMotifModified || isExamensModified || isTraitementsModified || isDevisModified || isConsultModified) {
        text = 'Modifications en cours';
        cls  = 'alert-warning';
      } else {
        const savedCount = Object.values(savedSections).filter(v => v).length;
        if (savedCount === 0) {
          text = 'Aucune modification';
          cls  = 'alert-secondary';
        } else if (savedCount < totalSections) {
          text = 'Partiellement mis à jour';
          cls  = 'alert-info';
        } else {
          text = 'Tout est sauvegardé';
          cls  = 'alert-success';
        }
      }
  
      $alert.addClass(cls);
      $('#saveStatusText').text(text);
    }
  
    // — Renvoie la liste des sections non sauvegardées
    function getUnsavedSections() {
      const list = [];
      if (isMotifModified)       list.push('Motif & Histoire');
      if (isExamensModified)     list.push('Examens');
      if (isTraitementsModified) list.push('Traitements & Documents');
      if (isDevisModified)       list.push('Devis');
      if (isConsultModified)     list.push('Consultation en cours');
      return list;
    }
  
    // — Vérifie si on peut clôturer (aucune modif non enregistrée)
    function canCloture() {
      return !(
        isMotifModified ||
        isExamensModified ||
        isTraitementsModified ||
        isDevisModified ||
        isConsultModified
      );
    }
  
    // — Watchers sur chaque formulaire pour détecter les changements
    $('#motifSoinsForm').on('input change', 'textarea', function() {
      isMotifModified = true;
      updateGlobalSaveStatus();
    });
    $('#examensForm, #toothContainer').on('input change', 'textarea', function() {
      isExamensModified = true;
      updateGlobalSaveStatus();
    });
    $('#traitementsDocumentsForm').on('input change', 'input, textarea', function() {
      isTraitementsModified = true;
      updateGlobalSaveStatus();
    });
    $('#devisForm').on('input change', 'input, textarea', function() {
      isDevisModified = true;
      updateGlobalSaveStatus();
    });
    $('#consultationEnCoursForm').on('input change', 'input, textarea, select', function() {
      isConsultModified = true;
      updateGlobalSaveStatus();
    });
  
    // — Empêche la fermeture/rafraîchissement si modifs non sauvées
    function askBeforeUnload(e) {
      if (!canCloture()) {
        e.preventDefault();
        e.returnValue = '';
      }
    }
    window.addEventListener('beforeunload', askBeforeUnload);
  
    // — Bouton Retour
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
  
    // — Chargement initial des données
    function loadData() {
      $.getJSON(`/api/fiche/${ficheId}/consultation/${consultId}/json`, function(data) {
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
            console.log($(`#tooth-${tooth}`));
            
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
  
        // Réinitialisation des flags
        isMotifModified = isExamensModified = isTraitementsModified = isDevisModified = isConsultModified = false;
        Object.keys(savedSections).forEach(k => savedSections[k] = false);
        updateGlobalSaveStatus();
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
  
    // — Gestion de la clôture
    $('#btnCloturerConsultation').on('click', function() {
      if (!canCloture()) {
        const unsaved = getUnsavedSections().join(', ');
        showToastModal({ message: `Sections non sauvegardées : ${unsaved}`, type: 'warning', duration: 2000 });
        return;
      }
      $('#modalClotureConsultation').modal('show');
    });
  
    $('#btnConfirmCloture').on('click', function() {
      $.ajax({
        url: `/api/fiche/${ficheId}/consultation/${consultId}/cloture`,
        method: 'POST',
        success() {
          showToastModal({ message: 'Consultation clôturée', type: 'success', duration: 3000 });
          window.location.href = '/admin/consultation/en-attente';
        },
        error() {
          showToastModal({ message: 'Erreur clôture consultation', type: 'error', duration: 3000 });
        }
      });
    });
  
    // — Boutons de sauvegarde
    $('#btnSaveMotifSoins').on('click', sendMotifUpdate);
    $('#btnSaveExamens').on('click', sendExamensUpdate);
    $('#btnSaveTraitementsDocuments').on('click', sendTraitementsUpdate);
    $('#btnSaveDevis').on('click', sendDevisUpdate);
    $('#btnSaveConsultationEnCours').on('click', sendConsultUpdate);
  
    // — Fonctions utilitaires pour blocs dynamiques
    function uniqueId(prefix = 'id') {
      return `${prefix}_${Date.now()}_${Math.floor(Math.random()*1000)}`;
    }
  
    // Documents
    $('#btnAddDocument').on('click', () => addDocumentBlock());
    function addDocumentBlock(doc = {}) {
      const uid = uniqueId('doc');
      const $blk = $(`
        <div class="document-block mb-3" id="${uid}" data-existing-url="${doc.url||''}">
          <div class="row gx-2">
            <div class="col-md-4"><label>Libellé</label><input type="text" class="form-control doc-libelle" value="${doc.libelle||''}"></div>
            <div class="col-md-3"><label>Date</label><input type="date" class="form-control doc-date" value="${doc.dateDossier||''}"></div>
            <div class="col-md-3 text-end d-flex align-items-end justify-content-end">
              <button type="button" class="btn btn-sm btn-outline-danger btn-remove-document"><i class="fas fa-trash"></i></button>
            </div>
          </div>
          <div class="row mt-2 gx-2">
            <div class="col-md-6"><label>Description</label><textarea class="form-control doc-description" rows="3">${doc.description||''}</textarea></div>
            <div class="col-md-6">
              <label>Fichier</label>
              ${ doc.url ? `<p><a href="/${doc.url}" target="_blank">Voir existant</a></p>` : '' }
              <input type="file" class="doc-fichier" name="documentsFiles[]">
            </div>
          </div>
        </div>
      `);
      $('#documentsContainer').append($blk);
    }
    $('#documentsContainer').on('click','.btn-remove-document',function(){
      $(this).closest('.document-block').remove();
      isTraitementsModified = true; updateGlobalSaveStatus();
    });
  
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
  
    // Actes médicaux
    $('#btnAddActe').on('click', () => {
      const blk = createActeBlock();
      $('#actesContainer').append(blk);
      isConsultModified = true; updateGlobalSaveStatus();
    });
    function createActeBlock(a={}) {
      const uid = uniqueId('acte');
      const $blk = $(`
        <div class="acte-block mb-3 border" id="${uid}">
          <div class="row gx-2">
            <div class="col-md-8">
              <div class="row gx-2">
                <div class="col-md-6 mb-2"><label>Dent</label><input type="text" class="form-control acte-dent" value="${a.dent||''}"></div>
                <div class="col-md-6 mb-2"><label>Type</label><input type="text" class="form-control acte-type" value="${a.type||''}"></div>
                <div class="col-md-6"><label>Prix</label><input type="number" step="0.01" class="form-control acte-prix" value="${a.prix||''}"></div>
                <div class="col-md-6"><label>Quantité</label><input type="number" class="form-control acte-qte" value="${a.quantite||1}"></div>
              </div>
            </div>
            <div class="col-md-3"><label>Description</label><textarea class="form-control acte-desc" rows="4">${a.description||''}</textarea></div>
            <div class="col-md-1 text-end d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-acte"><i class="fas fa-trash"></i></button></div>
          </div>
        </div>
      `);
      $blk.on('input','.form-control', function(){
        isConsultModified = true; updateGlobalSaveStatus();
      });
      $blk.on('click','.btn-remove-acte', function(){
        $(this).closest('.acte-block').remove();
        isConsultModified = true; updateGlobalSaveStatus();
      });
      return $blk;
    }
  });
  