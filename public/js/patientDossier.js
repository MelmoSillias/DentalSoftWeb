$(function(){
    // Extract patient ID from URL: /patient/{id}/dossier
    const pathParts = window.location.pathname.split('/');
    const patientId = pathParts[2];
    let dossierData = {};
  
    // Load dossier data via AJAX
    function loadData() {
      $.get(`/api/patient/${patientId}/dossier`, function(data) {
        dossierData = data;
        renderAll();
      });
    }
  
    // Render all sections
    function renderAll() {
      renderPatientInfo();
      renderCollections('allergies');
      renderCollections('antecedents');
      renderCollections('contactsUrgence');
      renderConsultations();
      renderRdvs();
      renderTimeline();
      bindEvents();
    }
  
    // --- 1. Patient info cards ---
    function renderPatientInfo() {
      const container = $('#patient-info-cards').empty();
      const fields = [
        { key: 'nom', label: 'Nom', type: 'text' },
        { key: 'prenom', label: 'Prénom', type: 'text' },
        { key: 'dateNaissance', label: 'Date de naissance', type: 'date' },
        { key: 'sexe', label: 'Sexe', type: 'select', options: ['Homme','Femme'] },
        { key: 'telephone', label: 'Téléphone', type: 'text' },
        { key: 'adresse', label: 'Adresse', type: 'text' },
        { key: 'groupeSanguin', label: 'Groupe sanguin', type: 'text' },
        { key: 'email', label: 'Email', type: 'email' }
      ];
      fields.forEach(f => {
        const card = $('<div>').addClass('card mb-3');
        const body = $('<div>').addClass('card-body');
        const label = $('<label>').text(f.label).attr('for', `field-${f.key}`).appendTo(body);
        let input;
        if(f.type === 'select') {
          input = $('<select>').addClass('form-control').attr('id', `field-${f.key}`).data('field', f.key);
          f.options.forEach(opt => {
            const o = $('<option>').val(opt).text(opt);
            if(dossierData.patient[f.key] === opt) o.prop('selected', true);
            input.append(o);
          });
        } else {
          input = $('<input>').addClass('form-control')
                   .attr('type', f.type)
                   .attr('id', `field-${f.key}`)
                   .data('field', f.key)
                   .val(dossierData.patient[f.key] || '');
        }
        input.appendTo(body);
        card.append(body);
        container.append(card);
      });
    }
  
    // --- 2. Collections (allergies, antecedents, contactsUrgence) ---
    function renderCollections(section) {
      const container = $(`#${section}-cards`).empty();
      const items = dossierData[section] || [];
      items.forEach(item => {
        const card = $('<div>').addClass('card mb-2').data('id', item.id || null);
        const body = $('<div>').addClass('card-body');
        if(section === 'contactsUrgence') {
          body.append(`<h6>${item.nom} ${item.prenom} (${item.relation})</h6>`);
          body.append(`<p>${item.telephone}</p>`);
        } else {
          body.append(`<h6>${item.nom}</h6>`);
          if(item.description) body.append(`<p>${item.description}</p>`);
        }
        const btn = $('<button>').addClass('close').html('&times;').attr('aria-label','Supprimer');
        btn.on('click', function(){
          $(this).closest('.card').remove();
          enableSave();
        });
        btn.appendTo(body);
        card.append(body);
        container.append(card);
      });
      // add-card
      const addCard = $('<div>').addClass('card add-card mb-2');
      const addBody = $('<div>').addClass('card-body');
      if(section === 'contactsUrgence') {
        addBody.append('<input class="form-control mb-1" id="add-'+section+'-nom" placeholder="Nom"/>');
        addBody.append('<input class="form-control mb-1" id="add-'+section+'-prenom" placeholder="Prénom"/>');
        addBody.append('<input class="form-control mb-1" id="add-'+section+'-relation" placeholder="Relation"/>');
        addBody.append('<input class="form-control mb-1" id="add-'+section+'-telephone" placeholder="Téléphone"/>');
      } else {
        addBody.append(`<input class="form-control mb-1" id="add-${section}-nom" placeholder="Nom"/>`);
        addBody.append(`<input class="form-control mb-1" id="add-${section}-description" placeholder="Description"/>`);
      }
      const addBtn = $('<button>').addClass('btn btn-sm btn-outline-primary')
                     .text('Ajouter')
                     .on('click', function(){
                       let newItem = { id: null,
                                       nom: $(`#add-${section}-nom`).val(),
                                       description: $(`#add-${section}-description`).val(),
                                       prenom: $(`#add-${section}-prenom`).val(),
                                       relation: $(`#add-${section}-relation`).val(),
                                       telephone: $(`#add-${section}-telephone`).val()
                                     };
                       dossierData[section].push(newItem);
                       renderCollections(section);
                       enableSave();
                     });
      addBody.append(addBtn);
      addCard.append(addBody);
      container.append(addCard);
    }
  
    // --- 3. Consultations table ---
    function renderConsultations() {
      const tbody = $('#consultations-table tbody').empty();
      (dossierData.consultations || []).forEach(c => {
        const tr = $('<tr>');
        tr.append(`<td><a href="#" class="toggle" data-target="#consult-detail-${c.id}" data-toggle="collapse"><i class="fa fa-chevron-down"></i></a></td>`);
        tr.append(`<td>${c.id}</td>`);
        tr.append(`<td>${formatDate(c.dateDebut)}</td>`);
        tr.append(`<td>${c.medecinNom}</td>`);
        tr.append(`<td>${c.type||''}</td>`);
        tr.append(`<td>${c.state===0?'Clôturée':'En cours'}</td>`);
        tr.append(`<td>
                     <button class="btn btn-sm btn-info btn-view-consult" data-id="${c.id}">Voir</button>
                     ${c.state!==0?`<button class="btn btn-sm btn-success btn-close-consult" data-id="${c.id}">Clôturer</button>`:''}
                   </td>`);
        tbody.append(tr);
        // detail row
        const det = $('<tr>').addClass('collapse').attr('id',`consult-detail-${c.id}`);
        const td = $('<td>').attr('colspan',7);
        let html = `<p><strong>Diagnostic:</strong> ${c.diagnostic||''}</p>
                    <p><strong>Remarques:</strong> ${c.remarques||''}</p>`;
        // documents / ordonnaces if present
        if(c.documents) {
          html += '<p><strong>Documents:</strong><ul>';
          c.documents.forEach(d=> html+=`<li>${d.libelle} (${formatDate(d.date)})</li>`);
          html += '</ul></p>';
        }
        if(c.ordonnances) {
          html += '<p><strong>Ordonnances:</strong><ul>';
          c.ordonnances.forEach(o=> html+=`<li>${o.libelle} (${formatDate(o.date)})</li>`);
          html += '</ul></p>';
        }
        td.html(html).appendTo(det);
        tbody.append(det);
      });
    }
  
    // --- 4. RDVs table ---
    function renderRdvs() {
      const tbody = $('#rdvs-table tbody').empty();
      (dossierData.rdvs || []).forEach(r => {
        const tr = $('<tr>').addClass('rdv-row').data('id', r.id);
        tr.append(`<td>${formatDate(r.dateHeure)}</td>`);
        tr.append(`<td>${r.salle}</td>`);
        tr.append(`<td>${r.medecinNom}</td>`);
        tr.append(`<td>${r.patientNom||''}</td>`);
        tr.append(`<td>${r.statut}</td>`);
        tbody.append(tr);
      });
    }
  
    // --- 5. Timeline ---
    function renderTimeline() {
      const tc = $('#timeline-consultations').empty();
      (dossierData.consultations||[]).forEach((c,i,arr) => {
        const card = $('<div>').addClass('card timeline-card mx-2').css('min-width','150px');
        const body = $('<div>').addClass('card-body p-2');
        body.append(`<h6 class="card-title">${formatDate(c.dateDebut)}</h6>`);
        body.append(`<p class="card-text mb-1">${c.type||''}</p>`);
        body.append(`<button class="btn btn-sm btn-primary btn-view-consult" data-id="${c.id}">+ détails</button>`);
        card.append(body);
        tc.append(card);
        // arrow
        if(i < arr.length-1) {
          tc.append('<i class="fas fa-arrow-right align-self-center"></i>');
        }
      });
      const tr = $('#timeline-rdvs').empty();
      (dossierData.rdvs||[]).forEach(r=>{
        const card = $('<div>').addClass('card timeline-card border-info mx-2').css('min-width','150px');
        const body = $('<div>').addClass('card-body p-2');
        body.append(`<h6 class="card-title">${formatDate(r.dateHeure)}</h6>`);
        body.append(`<p class="card-text mb-1">${r.statut}</p>`);
        card.append(body);
        tr.append(card);
      });
    }
  
    // Bind events
    function bindEvents() {
      // enable save on info change
      $('#patient-info-cards').find('input, select').off().on('change', enableSave);
      // consultations: view and close
      $('#consultations-table').off().on('click','.btn-view-consult', function(){
        const id = $(this).data('id');
        window.location.href = `/consultation/${id}`;
      }).on('click','.btn-close-consult', function(){
        const id = $(this).data('id');
        $.post(`/api/consultation/${id}/cloture`, function(){
          loadData();
        });
      });
      // RDV click
      $('#rdvs-table').off().on('click','.rdv-row', function(){
        const id = $(this).data('id');
        window.location.href = `/rdv/${id}`;
      });
      // save button
      $('#btn-save-patient').off().on('click', saveData);
    }
  
    function enableSave() {
      $('#btn-save-patient').prop('disabled', false);
    }
  
    // Save data via AJAX PUT
    function saveData() {
      let payload = {
        patient: {},
        allergies: [], antecedents: [], contactsUrgence: []
      };
      // patient simple
      $('#patient-info-cards').find('input,select').each(function(){
        payload.patient[$(this).data('field')] = $(this).val();
      });
      // collections
      $('#allergies-cards .card:not(.add-card)').each(function(){
        payload.allergies.push({
          id: $(this).data('id'),
          nom: $(this).find('h6').text(),
          description: $(this).find('p').text()
        });
      });
      $('#antecedents-cards .card:not(.add-card)').each(function(){
        payload.antecedents.push({
          id: $(this).data('id'),
          nom: $(this).find('h6').text(),
          description: $(this).find('p').text()
        });
      });
      $('#contacts-cards .card:not(.add-card)').each(function(){
        payload.contactsUrgence.push({
          id: $(this).data('id'),
          nom: $(this).find('h6').text().split(' ')[0],
          prenom: $(this).find('h6').text().split(' ')[1],
          relation: $(this).find('h6').text().match(/\\((.*)\\)/)[1],
          telephone: $(this).find('p').text()
        });
      });
      $.ajax({
        url: `/api/patient/${patientId}/dossier`,
        method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function(){
          $('#btn-save-patient').prop('disabled', true);
          showToast('Données enregistrées');
          loadData();
        },
        error: function(){
          showToast('Erreur lors de l’enregistrement', false);
        }
      });
    }
  
    // Toast message
    function showToast(msg, success=true) {
      const toastHtml = `
        <div class="toast ${success?'bg-success':'bg-danger'} text-white" role="alert" data-delay="3000">
          <div class="toast-body">${msg}</div>
        </div>`;
      const $toast = $(toastHtml).appendTo('body');
      $toast.toast('show').on('hidden.bs.toast', function(){ $toast.remove(); });
    }
  
    // Helper: format ISO datetime
    function formatDate(iso){
      if(!iso) return '';
      const d = new Date(iso);
      return d.toLocaleString('fr-FR', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    }
  
    // initial load
    loadData();
  });