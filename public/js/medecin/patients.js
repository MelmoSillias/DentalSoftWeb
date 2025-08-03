$(document).ready(function () {

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
    
    let dataTable;
    function initDataTable() {
        dataTable = $('#patientTable').DataTable({
            ajax: {
                url: "/api/patients/medecin",
                dataSrc: ''
            },
            columns: [
                { data: 'fullname' }, 
                { data: 'age', width: '80px'},
                { data: 'sexe', width: '80px' },
                { data: 'telephone', width: '150px' },
                { data: 'adresse'},
                {
                    data: 'id',
                    title: 'Actions',
                    width: '182px',
                    render: function (data) {
                        return `
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-primary edit-btn" data-id="${data}">
                                    <i class="fas fa-edit"></i>
                                </button> 
                                <a href="/medecin/patient/${data}/dossier" class="btn btn-sm ml-2 btn-info text-white">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                                <button class="btn btn-sm btn-warning rdv-btn text-white ml-2" data-id="${data}">
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
                medecin_id: $('#rdvDoctor').val(),
                description: $('#rdvDescription').val()
            };

            $.ajax({
                url: `/api/patient/${formData.patient_id}/rdv/create`,
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

    function init() { 
        fetchDoctors(); 
        initDataTable(); 
        initRdvModal();
        sendRdvHandler();
        initPatientEdit(); 
    }

    init();
});
