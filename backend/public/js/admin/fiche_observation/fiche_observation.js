
// ===============================
// JS pour Fiche d'Observation
// ===============================

let isModifiedFiche = false;
let isModifiedConsultation = false;

function updateSaveStatus(text, type = 'secondary') {
  const indicator = document.getElementById('saveStatusIndicator');
  const textEl = document.getElementById('saveStatusText');
  if (!indicator || !textEl) return;

  indicator.className = 'position-fixed top-0 end-0 m-3 alert alert-' + type;
  textEl.textContent = text;
}

// Écouteurs de modification
document.querySelectorAll('#ficheObservationForm input, #ficheObservationForm textarea, #ficheObservationForm select').forEach(el => {
  el.addEventListener('change', () => {
    isModifiedFiche = true;
    updateSaveStatus('Modifications en cours', 'warning');
  });
});
document.querySelectorAll('#consultationForm input, #consultationForm textarea, #consultationForm select').forEach(el => {
  el.addEventListener('change', () => {
    isModifiedConsultation = true;
    updateSaveStatus('Modifications en cours', 'warning');
  });
});

// Blocage du départ
window.onbeforeunload = function () {
  if (isModifiedFiche || isModifiedConsultation) return 'Modifications non sauvegardées';
};
document.getElementById('btnRetour').addEventListener('click', function () {
  if (isModifiedFiche || isModifiedConsultation) {
    const modal = new bootstrap.Modal(document.getElementById('modalQuitConfirm'));
    modal.show();
  } else {
    window.history.back();
  }
});
document.getElementById('btnQuitConfirmed').addEventListener('click', function () {
  window.location.href = document.referrer || '/';
});

// Ajout dynamique de blocs
document.getElementById('btnAddExamen').addEventListener('click', function () {
  const container = document.getElementById('examensContainer');
  const index = container.children.length;
  container.insertAdjacentHTML('beforeend', `
    <div class="row border rounded p-2 mb-2 bg-light">
      <div class="col-md-4">
        <input type="date" name="examens[${index}][date]" class="form-control" />
      </div>
      <div class="col-md-4">
        <input type="text" name="examens[${index}][designation]" class="form-control" placeholder="Désignation" />
      </div>
      <div class="col-md-4">
        <input type="text" name="examens[${index}][resultat]" class="form-control" placeholder="Résultat" />
      </div>
    </div>
  `);
  isModifiedFiche = true;
  updateSaveStatus('Modifications en cours', 'warning');
});

document.getElementById('btnAddDocument').addEventListener('click', function () {
  const container = document.getElementById('documentsContainer');
  const index = container.children.length;
  container.insertAdjacentHTML('beforeend', `
    <div class="row border rounded p-2 mb-2 bg-light">
      <div class="col-md-4">
        <input type="text" name="documents[${index}][libelle]" class="form-control" placeholder="Libellé" />
      </div>
      <div class="col-md-3">
        <input type="date" name="documents[${index}][validite]" class="form-control" />
      </div>
      <div class="col-md-5">
        <input type="file" name="documents[${index}][fichier]" class="form-control" />
      </div>
    </div>
  `);
  isModifiedFiche = true;
  updateSaveStatus('Modifications en cours', 'warning');
});

document.getElementById('btnAddActe').addEventListener('click', function () {
  const container = document.getElementById('actesContainer');
  const index = container.children.length;
  container.insertAdjacentHTML('beforeend', `
    <div class="row border rounded p-2 mb-2 bg-light">
      <div class="col-md-2"><input type="text" name="actes[${index}][dent]" class="form-control" placeholder="Dent" /></div>
      <div class="col-md-3"><input type="text" name="actes[${index}][type]" class="form-control" placeholder="Type" /></div>
      <div class="col-md-3"><input type="text" name="actes[${index}][description]" class="form-control" placeholder="Description" /></div>
      <div class="col-md-2"><input type="number" name="actes[${index}][prix]" class="form-control" placeholder="Prix" /></div>
      <div class="col-md-2"><input type="number" name="actes[${index}][quantite]" class="form-control" placeholder="Qté" /></div>
    </div>
  `);
  isModifiedConsultation = true;
  updateSaveStatus('Modifications en cours', 'warning');
});

// Devis
document.getElementById('btnAddDevis').addEventListener('click', () => {
  const modal = new bootstrap.Modal(document.getElementById('modalAddDevis'));
  modal.show();
});
document.getElementById('btnSubmitDevis').addEventListener('click', () => {
  // POST vers API avec les données du devis
  showToastModal('Devis enregistré', 'success');
  bootstrap.Modal.getInstance(document.getElementById('modalAddDevis')).hide();
});

// Clôture consultation
document.getElementById('btnCloturerConsultation').addEventListener('click', () => {
  new bootstrap.Modal(document.getElementById('modalClotureConsultation')).show();
});
document.getElementById('btnConfirmCloture').addEventListener('click', () => {
  // POST vers API pour clôturer consultation
  showToastModal('Consultation clôturée avec succès', 'success');
  window.location.href = '/consultations';
});
