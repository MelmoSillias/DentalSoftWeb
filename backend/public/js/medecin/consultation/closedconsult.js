$(document).ready(function () { 
    $('#closedConsultationsTable').DataTable({
        ajax: {
            url: '/api/consultations/closed',
            dataSrc: ''
        },
        columns: [
            {data : 'id'},
            { data: 'date' },
            { data: 'patient' },
            { data: 'medecin' },  
            {
              data: null,
              render: function(_, _, row) {
                // Crée un bouton pour le menu déroulant
                let html = `
                      <a class="btn btn-sm btn-view-consultation btn-primary"
                        href="#" data-id="${row.id}">
                        <i class="fas fa-eye me-1"></i>
                      </a>
                      <a class="btn btn-sm btn-view-dossier btn-info"
                        href="#" data-patient-id="${row.patientId}">
                        <i class="fas fa-folder-open me-1"></i> 
                      </a>
                      <a class="btn btn-sm btn-modify-facture btn-secondary ${row.factstate === 0 ? '' : 'd-none'}"
                        href="#" data-consult-id="${row.id}">
                        <i class="fas fa-edit me-1"></i>
                      </a>
                `;
                return html;
              },
              orderable: false,
              searchable: false
            }

        ],
        language: {
            "sEmptyTable": "Aucune donnée disponible dans le tableau",
            "sInfo": "Affichage de l'élément _START_ à _END_ sur _TOTAL_ éléments",
            "sInfoEmpty": "Affichage de l'élément 0 à 0 sur 0 élément",
            "sInfoFiltered": "(filtré à partir de _MAX_ éléments au total)",
            "sLengthMenu": "Afficher _MENU_ éléments",
            "sLoadingRecords": "Chargement...",
            "sProcessing": "Traitement...",
            "sSearch": "Rechercher :",
            "sZeroRecords": "Aucun élément correspondant trouvé",
            "oPaginate": {
                "sFirst": "Premier",
                "sLast": "Dernier",
                "sNext": "Suivant",
                "sPrevious": "Précédent"
            }
        },
        responsive: true // Enable responsiveness
    });

    
      
});

// 1. Voir le dossier patient
$(document).on('click', '.btn-view-dossier', function() {
  const patientId = $(this).data('patient-id');
  // Adaptez l’URL selon votre route Symfony
  window.location.href = `/medecin/patient/${patientId}/dossier`;
});

$(document).on('click', '.btn-view-consultation', function() {
  const id = $(this).data('id');
  $.getJSON(`/medecin/consultation/${id}/details.json`, function(data) {
    $('#detail-id').text(data.id);
    $('#detail-date').text(data.date);
    $('#detail-patient').text(data.patient);
    $('#detail-medecin').text(data.medecin || '—');
    $('#detail-infirmier').text(data.infirmier || '—');
    $('#detail-salle').text(data.salle || '—'); 
    $('#detail-note').text(data.noteSeance || '—');

    // — Actes médicaux
    const $tbody = $('#detail-actes').empty();
    data.actes.forEach(a => {
    const total = (a.quantite * a.prix).toFixed(2);
    $tbody.append(`
        <tr>
        <td>${a.dent}</td>
        <td>${a.type}</td>
        <td>${a.description}</td>
        <td class="text-end">${a.quantite}</td>
        <td class="text-end">${a.prix.toFixed(2)}</td>
        <td class="text-end">${total}</td>
        </tr>
    `);
    });
  
      // Affiche le modal
    const modal = new bootstrap.Modal(document.getElementById('consultationDetailsModal'));
    modal.show();
    }).fail(function() {
      showToastModal({
        message: 'Impossible de charger les détails de la consultation.',
        type: 'error',
        duration: 3000
      });
    });
  });

      $(document).on('click', '.btn-closer', function() {
        $(this).closest('.modal').modal('hide'); 
      })

// 2. Modifier la facture
$(document).on('click', '.btn-modify-facture', function() {
  const consultId = $(this).data('consult-id');
  openModifyFactureModal(consultId);
});


function openModifyFactureModal(consultId) {
  $('#factureLinesContainer').empty();
  $('#factureTotal').text('0.00');
  $('#btnSaveFacture').data('id', consultId); 

  // Récupérer les lignes de la facture via AJAX
  $.get(`/api/consultations/${consultId}/facture`, function(lines) {
    // lines = [ { dent, type, prix, quantite, description, idLigne }, ... ]
    lines.forEach(l => {
      const blk = createFactureLineBlock(l);
      $('#factureLinesContainer').append(blk);
    });
    recalcFactureTotal();
    $('#modifyFactureModal').modal('show');
  });
}

function uniqueId(prefix = 'id') {
      return `${prefix}_${Date.now()}_${Math.floor(Math.random()*1000)}`;
    }

// Fonction de création d’un bloc « ligne de facture » (adaptée de createActeBlock)
function createFactureLineBlock(data = {}) {
  const uid = uniqueId('ligne'); // même fonction uniqueId
  const $blk = $(`
    <div class="ligne-facture mb-3 border p-2" id="${uid}">
      <div class="row gx-2"> 
        <div class="col-md-4">
          <label>Description</label>
          <textarea class="form-control ligne-desc" rows="2">${data.designation||''}</textarea>
        </div>
        <div class="col-md-2">
          <label>Prix (F CFA)</label>
          <input type="number" step="0.01" class="form-control ligne-prix" value="${data.montant||0}">
        </div>
        <div class="col-md-2">
          <label>Quantité</label>
          <input type="number" class="form-control ligne-qte" value="${data.quantite||1}">
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
  $blk.on('click', '.btn-remove-ligne', function(){
    $blk.remove();
    recalcFactureTotal();
  });
  return $blk;
}

// Ajout d’une nouvelle ligne vide
$('#btnAddLigne').on('click', function(){
  $('#factureLinesContainer').append(createFactureLineBlock());
  recalcFactureTotal();
});

// Recalcul du total TTC
function recalcFactureTotal(){
  let total = 0;
  $('#factureLinesContainer .ligne-facture').each(function(){
    const prix = parseFloat($(this).find('.ligne-prix').val()) || 0;
    const qte  = parseInt($(this).find('.ligne-qte').val()) || 0;
    total += prix * qte;
  });
  $('#factureTotal').text(total.toFixed(2));
}

// Enregistrement de la facture modifiée
$('#btnSaveFacture').on('click', function(){
  const payload = [];
  const consultId = $(this).data('id')
  $('#factureLinesContainer .ligne-facture').each(function(){
    payload.push({ 
      prix: parseFloat($(this).find('.ligne-prix').val()),
      quantite: parseInt($(this).find('.ligne-qte').val()),
      description: $(this).find('.ligne-desc').val()
      // ajoutez éventuellement l’ID de la ligne si vous en avez besoin
    });
  });

  $.ajax({
    url: `/api/consultations/${consultId}/facture/update`, // stockez-le dans une variable globale
    method: 'PUT',
    contentType: 'application/json',
    data: JSON.stringify({ lignes: payload }),
    success: function() {
      $('#modifyFactureModal').modal('hide');
      // rafraîchir le DataTable
      $('#closedConsultationsTable').DataTable().ajax.reload(null, false);
    },
    error: function(err) {
      console.error(err);
      alert('Erreur lors de l’enregistrement de la facture');
    }
  });

  
  
});
