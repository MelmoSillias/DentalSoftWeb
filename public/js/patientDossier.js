$(function () {
  const patientId = window.location.pathname.split('/')[2];
  let dossierData = {};

  function loadData() {
    $.get(`/api/patient/${patientId}/dossier`, function (data) {
      dossierData = data;
      renderAll();
    });
  }

  function renderAll() {
    renderPatientInfo();
    renderCollections('allergies');
    renderCollections('antecedents');
    renderCollections('contactsUrgence');
    renderConsultations();
    renderRdvs();
    bindEvents();
  }

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

  function renderEditableCards(section) {
    const container = $(`#${section}-cards`).empty().addClass('row');
    const items = dossierData[section] || [];

    items.forEach((item, index) => {
      const col = $('<div>').addClass('col-md-4');
      const card = $('<div>').addClass('card mb-3').data('id', item.id || null);
      const body = $('<div>').addClass('card-body');

      if (section === 'contactsUrgence') {
        body.append(`<input class="form-control mb-2" placeholder="Nom" value="${item.nom || ''}"/>`);
        body.append(`<input class="form-control mb-2" placeholder="Prénom" value="${item.prenom || ''}"/>`);
        body.append(`<input class="form-control mb-2" placeholder="Relation" value="${item.relation || ''}"/>`);
        body.append(`<input class="form-control mb-2" placeholder="Téléphone" value="${item.telephone || ''}"/>`);
      } else {
        body.append(`<input class="form-control mb-2" placeholder="Nom" value="${item.nom || ''}"/>`);
        body.append(`<textarea class="form-control mb-2" placeholder="Description">${item.description || ''}</textarea>`);
      }

      const removeBtn = $('<button>').addClass('btn btn-sm btn-outline-danger').text('Supprimer').on('click', function () {
        $(this).closest('.col-md-4').remove();
        enableSave();
      });

      body.append(removeBtn);
      card.append(body);
      col.append(card);
      container.append(col);
    });
  }

  function enableSave() {
    $('#btn-save-patient').prop('disabled', false);
  }

  function bindEvents() {
    $('#btn-add-allergy').on('click', function () {
      dossierData.allergies = dossierData.allergies || [];
      dossierData.allergies.push({ nom: '', description: '' });
      renderEditableCards('allergies');
      enableSave();
    });

    $('#btn-add-antecedent').on('click', function () {
      dossierData.antecedents = dossierData.antecedents || [];
      dossierData.antecedents.push({ nom: '', description: '' });
      renderEditableCards('antecedents');
      enableSave();
    });

    $('#btn-add-contact').on('click', function () {
      dossierData.contactsUrgence = dossierData.contactsUrgence || [];
      dossierData.contactsUrgence.push({ nom: '', prenom: '', relation: '', telephone: '' });
      renderEditableCards('contactsUrgence');
      enableSave();
    });

    $('#btn-save-patient').on('click', saveData);
  }

  function saveData() {
    let payload = {
      patient: {},
      allergies: [],
      antecedents: [],
      contactsUrgence: []
    };

    $('#patient-info-cards input, #patient-info-cards select').each(function () {
      payload.patient[$(this).data('field')] = $(this).val();
    });

    $('#allergies-cards .card').each(function () {
      payload.allergies.push({
        id: $(this).data('id'),
        nom: $(this).find('input').val(),
        description: $(this).find('textarea').val()
      });
    });

    $('#antecedents-cards .card').each(function () {
      payload.antecedents.push({
        id: $(this).data('id'),
        nom: $(this).find('input').val(),
        description: $(this).find('textarea').val()
      });
    });

    $('#contacts-cards .card').each(function () {
      const inputs = $(this).find('input');
      payload.contactsUrgence.push({
        id: $(this).data('id'),
        nom: inputs.eq(0).val(),
        prenom: inputs.eq(1).val(),
        relation: inputs.eq(2).val(),
        telephone: inputs.eq(3).val()
      });
    });

    $.ajax({
      url: `/api/patient/${patientId}/dossier`,
      method: 'PUT',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      success: function () {
        $('#btn-save-patient').prop('disabled', true);
        showToast('Dossier mis à jour');
        loadData();
      },
      error: function () {
        showToast('Erreur de sauvegarde', false);
      }
    });
  }

  function showToast(msg, success = true) {
    const toastHtml = `
      <div class="toast ${success ? 'bg-success' : 'bg-danger'} text-white" role="alert" data-delay="3000">
        <div class="toast-body">${msg}</div>
      </div>`;
    const $toast = $(toastHtml).appendTo('body');
    $toast.toast('show').on('hidden.bs.toast', () => $toast.remove());
  }

  function renderRdvs() {
    const tbody = $('#rdvs-table tbody').empty();
    (dossierData.rdvs || []).forEach(r => {
      const tr = $('<tr>').addClass('rdv-row').data('id', r.id);
      tr.append(`<td>${formatDate(r.dateHeure)}</td>`);
      tr.append(`<td>${r.salle}</td>`);
      tr.append(`<td>${r.medecinNom}</td>`);
      tr.append(`<td>${r.patientNom || ''}</td>`);
      tr.append(`<td>${r.statut}</td>`);
      tbody.append(tr);
    });

    $('#rdvs-table').off().on('click', '.rdv-row', function () {
      const id = $(this).data('id');
      window.location.href = `/rdv/${id}`;
    });
  }

  function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  loadData();
});
