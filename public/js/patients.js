$(document).ready(function () {
    function initModals() {
        $(document).on('click', '.consult-btn', function () {
            const patientId = $(this).data('id');
        
            $.get(`/api/patient/${patientId}/consultation-en-cours`, function (response) {
                if (response.hasActive) {
                    showToastModal({
                        message: "Une consultation est déjà en cours pour ce patient.",
                        type: "warning",
                        duration: 4000
                    });
                    return;
                }
        
                // Sinon, ouvrir le modal normalement
                loadModesPaiement();
                $('#consultationForm').data('patient-id', patientId);
        
                $.get(`/api/patient/${patientId}`, function (data) {
                    $('#patient').val(data.nom + " " + data.prenom);
                }).fail(function () {
                    showToastModal({
                        message: "Erreur lors de la récupération du patient",
                        type: "error",
                        duration: 3000
                    });
                });
        
                $('#createConsultationModal').modal('show');
            }).fail(function () {
                showToastModal({
                    message: "Erreur lors de la vérification de la consultation en cours.",
                    type: "error",
                    duration: 3000
                });
            });
        });
        

        $('#createConsultationModal').on('hidden.bs.modal', function () {
            $('#consultationForm')[0].reset();
            $('#consultationForm').removeData('patient-id');
        });
    }

    function fetchDoctors() {
        $.get('/api/medecins', function (data) {
            const medecinSelect = $('#medecin');
            medecinSelect.empty();
            $.each(data, function (index, medecin) {
                medecinSelect.append(`<option value="${medecin.id}">${medecin.nom} ${medecin.prenom}</option>`);
            });
        }).fail(function () {
            showToastModal({
              message: "Erreur lors de la récupération des médecins",
              type: "error",
              duration: 3000
            });
        });
    }

    function loadModesPaiement() {
        $.get('/api/modes-paiement', function(data) {
            const select = $('#modePaiement');
            select.empty().append('<option disabled selected>Choisir un mode</option>');
            data.forEach(mode => {
                if (mode.actif) {
                    select.append(`<option value="${mode.id}">${mode.libelle}</option>`);
                }
            });
        }).fail(function() {
            showToastModal({
              message: "Erreur lors du chargement des modes de paiement.",
              type: "error",
              duration: 3000
            });
        });
    }

    function handleConsultationForm() {
        $('#consultationForm').submit(function (e) {
            e.preventDefault();
            const formData = {
                patient_id: $(this).data('patient-id'),
                medecin_id: $('#medecin').val(),
                payant: $('input[name="payant"]:checked').val(),
                mode_paiement_id: $('input[name="payant"]:checked').val() ? $('#modePaiement').val() : null
            };

            $.ajax({
                url: '/api/consultation/create',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                success: function (response) {
                    $('#createConsultationModal').modal('hide');
                    if (response.success) {
                        showToastModal({
                          message: 'Consultation créée avec succès !',
                          type: 'success'
                        });
                        window.location.reload();
                    } else {
                        showToastModal({
                          message: 'Erreur : ' + response.error,
                          type: 'error',
                          duration: 3000
                        });
                    }
                },
                error: function () {
                    showToastModal({
                      message: 'Une erreur est survenue lors de la création',
                      type: 'error',
                      duration: 3000
                    });
                }
            });
        });
    }

    let dataTable;
    function initDataTable() {
        dataTable = $('#patientTable').DataTable({
            ajax: {
                url: "/api/patients",
                dataSrc: ''
            },
            layout: {
                topStart: {
                    buttons: [
                        {
                            text: 'Nouveau Patient',
                            className: 'btn btn-success mb-2',
                            action: function () {
                                $('#addPatientModal').modal('show');
                            }
                        },
                        { extend: 'spacer', style: 'bar', className: 'mb-2' },
                        {
                            extend: 'pdfHtml5',
                            text: 'Exporter en PDF',
                            className: 'btn mb-2',
                            title: 'Liste des patients Orodent',
                            filename: 'liste_des_patients_orodent',
                            messageTop: 'PDF créé par PDFMake avec Buttons pour DataTables.',
                            customize: function (doc) {
                                doc.pageSize = 'A4';
                                doc.content[0].margin = [0, 0, 0, 12];
                                const now = new Date();
                                const jsDate = now.getDate() + '-' + (now.getMonth() + 1) + '-' + now.getFullYear();
                                doc.content.push({
                                    text: 'Date d\'exportation: ' + jsDate,
                                    alignment: 'right',
                                    margin: [0, 10]
                                });
                            }
                        }
                    ]
                }
            },
            columns: [
                { data: 'nom' },
                { data: 'prenom' },
                { data: 'age' },
                { data: 'sexe' },
                { data: 'telephone' },
                { data: 'adresse' },
                {
                    data: 'id',
                    title: 'Actions',
                    render: function (data) {
                        return `
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-primary edit-btn" data-id="${data}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-success consult-btn" data-id="${data}">
                                    <i class="fas fa-stethoscope"></i>
                                </button>
                                <a href="/patient/${data}/dossier" class="btn btn-sm btn-info text-white">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                                <button class="btn btn-sm btn-warning rdv-btn text-white" data-id="${data}">
                                    <i class="fas fa-calendar-plus"></i>
                                </button>
                            </div>`;
                    },
                    orderable: false,
                    searchable: false
                }
            ],
            processing: true,
            serverSide: false,
            language: {
                sEmptyTable: "Aucune donnée disponible",
                sInfo: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
                sLengthMenu: "Afficher _MENU_ éléments",
                sSearch: "Rechercher :",
                oPaginate: { sNext: "Suivant", sPrevious: "Précédent" }
            }
        });
    }

    function initPatientEdit() {
        $(document).on('click', '.edit-btn', function () {
            const patientId = $(this).data('id');
            $('#editModal').modal('show');

            $.get(`/api/patient/${patientId}`, function (data) {
                $('#editPatientId').val(data.id);
                $('#editNom').val(data.nom);
                $('#editPrenom').val(data.prenom);
                $('#editTelephone').val(data.telephone);
                $('#editAdresse').val(data.adresse);
                $('#editGroupeSanguin').val(data.groupeSanguin || '');
                if (data.contactUrgence) {
                    $('#editUrgenceNom').val(data.contactUrgence.nom || '');
                    $('#editUrgenceTelephone').val(data.contactUrgence.telephone || '');
                    $('#editUrgenceLien').val(data.contactUrgence.lienParente || '');
                } else {
                    $('#editUrgenceNom').val('');
                    $('#editUrgenceTelephone').val('');
                    $('#editUrgenceLien').val('');
                }
            }).fail(function () {
                showToastModal({
                  message: "Erreur lors du chargement du patient",
                  type: "error",
                  duration: 3000
                });
            });
        });

        $('#editPatientForm').submit(function (e) {
            e.preventDefault();
            const patientId = $('#editPatientId').val();
            const formData = {
                nom: $('#editNom').val(),
                prenom: $('#editPrenom').val(),
                telephone: $('#editTelephone').val(),
                adresse: $('#editAdresse').val(),
                groupeSanguin: $('#editGroupeSanguin').val(),
                contactUrgence: {
                    nom: $('#editUrgenceNom').val(),
                    telephone: $('#editUrgenceTelephone').val(),
                    lienParente: $('#editUrgenceLien').val()
                }
            };

            $.ajax({
                url: `/api/patient/${patientId}/update`,
                type: "POST",
                data: JSON.stringify(formData),
                contentType: "application/json",
                success: function () {
                    $('#editModal').modal('hide');
                    dataTable.ajax.reload();
                    showToastModal({
                      message: "Patient mis à jour !",
                      type: "success"
                    });
                },
                error: function () {
                    showToastModal({
                      message: "Erreur lors de la mise à jour",
                      type: "error",
                      duration: 3000
                    });
                }
            });
        });
    }

    function initRdvModal() {
        $(document).on('click', '.rdv-btn', function () {
            const patientId = $(this).data('id');
            $('#rdvPatientId').val(patientId);
            $('#rdvPatientName').val($(this).closest('tr').find('td:first').text());
            $('#rdvModal').modal('show');

            $.get('/api/medecins', function (data) {
                const rdvDoctorSelect = $('#rdvDoctor');
                rdvDoctorSelect.empty();
                $.each(data, function (i, m) {
                    rdvDoctorSelect.append(`<option value="${m.id}">${m.nom} ${m.prenom}</option>`);
                });
            }).fail(function () {
                showToastModal({
                  message: "Erreur lors de la récupération des médecins",
                  type: "error",
                  duration: 3000
                });
            });
        });
    }

    function sendRdvHandler() {
        $('#rdvForm').submit(function (e) {
            e.preventDefault();
            const formData = {
                patient_id: $('#rdvPatientId').val(),
                date: $('#rdvDate').val(),
                time: $('#rdvTime').val(),
                salle_id: $('#rdvSalle').val(),
                medecin_id: $('#rdvDoctor').val(),
                description: $('#rdvDescription').val()
            };

            $.ajax({
                url: '/api/rdv/create',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                success: function (response) {
                    $('#rdvModal').modal('hide');
                    if (response.success) {
                        showToastModal({
                          message: "Rendez-vous créé avec succès !",
                          type: "success"
                        });
                    } else {
                        showToastModal({
                          message: "Erreur : " + response.error,
                          type: "error",
                          duration: 3000
                        });
                    }
                },
                error: function () {
                    showToastModal({
                      message: "Erreur lors de la création du RDV",
                      type: "error",
                      duration: 3000
                    });
                }
            });
        });
    }

    function handleAddPatientForm() {
        $('#patientForm').submit(function (e) {
            e.preventDefault();
            const formData = {
                nom: $('#nom').val(),
                prenom: $('#prenom').val(),
                dateNaissance: $('#dateNaissance').val(),
                sexe: $('#sexe').val(),
                telephone: $('#telephone').val(),
                adresse: $('#adresse').val(),
                groupeSanguin: $('#groupeSanguin').val(),
                contactUrgence: {
                    nom: $('input[name="urgence_nom"]').val(),
                    telephone: $('input[name="urgence_telephone"]').val(),
                    lienParente: $('input[name="urgence_lien"]').val()
                }
            };

            $.ajax({
                url: '/api/patient/add',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                success: function () {
                    $('#addPatientModal').modal('hide');
                    $('#patientForm')[0].reset();
                    dataTable.ajax.reload();
                    showToastModal({
                      message: 'Patient ajouté avec succès !',
                      type: "success"
                    });
                },
                error: function (xhr) {
                    showToastModal({
                      message: "Erreur lors de l'ajout : " + xhr.responseText,
                      type: "error",
                      duration: 3000
                    });
                }
            });
        });
    }

    $('input[name="payant"]').on('change', toggleModePaiementVisibility);

    $('#createConsultationModal').on('show.bs.modal', function () {
        $('#consultationForm')[0].reset();
        $('#modePaiementContainer').addClass('d-none');
        $('#modePaiement').removeAttr('required').empty();
    });

    function toggleModePaiementVisibility() {
        const isPayant = $('input[name="payant"]:checked').val() === '1';
        if (isPayant) {
            $('#modePaiementContainer').removeClass('d-none');
            $('#modePaiement').attr('required', true);
            loadModesPaiement();
        } else {
            $('#modePaiementContainer').addClass('d-none');
            $('#modePaiement').removeAttr('required');
        }
    }

    function init() {
        initModals();
        fetchDoctors();
        handleConsultationForm();
        initDataTable();
        handleAddPatientForm();
        initRdvModal();
        sendRdvHandler();
        initPatientEdit();
        loadModesPaiement();
    }

    init();
});
