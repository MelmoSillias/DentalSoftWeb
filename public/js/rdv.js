$(document).ready(function() {
    let currentDate = new Date();
    let selectedRdvId = null;

    function generateDateSlider() {
        const $dateSlider = $('#date-slider');
        $dateSlider.empty();
        for (let i = -7; i <= 7; i++) {
            const d = new Date(currentDate);
            d.setDate(currentDate.getDate() + i);
            const dateStr = d.toISOString().split('T')[0];
            const $dateItem = $('<div>', {
                class: 'date-item',
                'data-date': dateStr,
                text: d.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })
            });
            if (d.toDateString() === currentDate.toDateString()) $dateItem.addClass('active');
            $dateItem.on('click', function() {
                currentDate = new Date($(this).data('date'));
                updateDateInputs();
                generateDateSlider();
                reloadAppointments();
                loadStats() 
            });
            $dateSlider.append($dateItem);
        }
    }

    function updateDateInputs() {
        $('#date-picker').val(currentDate.toISOString().split('T')[0]);
    }

    $('#date-picker').on('change', function() {
        currentDate = new Date($(this).val());
        generateDateSlider();
        reloadAppointments();
        loadStats() 
    });

    $('#today-btn').on('click', function() {
        currentDate = new Date();
        generateDateSlider();
        reloadAppointments();
        loadStats() 
    });

    function reloadAppointments() {
        const url = `/api/rdvs/${currentDate.toISOString().split('T')[0]}`;
        $('.grid-cell').each(function() {
            const $cell = $(this);
            const $addBtn = $cell.find('.add-rdv-btn').detach();
            $cell.empty().append($addBtn.show());
        });
        $.getJSON(url, function(data) {
            data.forEach(function(rdv) {
                const rdvDate = new Date(rdv.dateRdv);
                const hour = rdvDate.getHours();
                const minutes = rdvDate.getMinutes();
                const slot = minutes < 15 ? '00' : minutes < 30 ? '15' : minutes < 45 ? '30' : '45';
                console.log(rdv.medecin)
                const $cell = $(`.grid-cell[data-hour="${hour}"][data-minute="${slot}"][data-medecin="${rdv.medecin_id}"]`);
                if ($cell.length) {
                    $cell.find('.add-rdv-btn').hide();
                    const statusClass = rdv.statut === 0 ? 'primary' :
                                        rdv.statut === 1 ? 'success' :
                                        rdv.statut === -1 ? 'warning' :
                                        'danger';
                    const actionButtons = rdv.statut === 0 ? `
                        <div class="rdv-actions mt-4"> 
                            <button class="btn btn-sm btn-success btn-validate" data-id="${rdv.id}"><i class="fas fa-check"></i></button>
                            <button class="btn btn-sm btn-warning btn-report" data-id="${rdv.id}" data-patient="${rdv.patient}" data-medecin="${rdv.medecin_id}" data-datecreation="${rdv.dateCreation}"><i class="fas fa-calendar-alt"></i></button>
                            <button class="btn btn-sm btn-danger btn-cancel" data-id="${rdv.id}"><i class="fas fa-times"></i></button>
                        </div>` : '';
                    const rdvHtml = `
                        <div class="rdv-card card card-outline-${statusClass} fade-in ">
                            <div class="rdv-content card-body">
                                <strong>${rdv.patient}</strong><br>
                                <small>${rdv.dateCreation}</small>

                                ${rdv.reportedAt ? `<small class='text-warning'>${rdv.reportedAt}</small>` : ''} 

                                ${actionButtons}
                            </div>
                        </div>`;
                    $cell.append(rdvHtml);
                }
            });
        }).fail(function() {
            showToastModal({ message: "Erreur de chargement des rendez-vous", type: "error", duration: 3000 });
        });
    }

    function loadStats() {
        const date = currentDate.toISOString().split('T')[0];
        const url = `/api/rdvs/stats/${date}`;

        $.getJSON(url, function(data) {
            $('#stats-pending').text(data.pending);
            $('#stats-validated').text(data.validated);
            $('#stats-postponed').text(data.postponed);
            $('#stats-cancelled').text(data.cancelled);
        }).fail(function() {
            showToastModal({ message: "Erreur de chargement des statistiques", type: "error", duration: 3000 });
        });
    }

    $('.btn-closer').on('click', function() {
        $(this).closest('.modal').modal('hide');
    });

    // Met à jour le statut et affiche un toast
    async function updateRdvStatus(id, statut) {
        try {
            const res = await fetch(`/api/rdv/${id}/${statut === 1 ? 'validate' : 'cancel'}`, { method: 'POST' });
            const json = await res.json();
            if (json.success) {
                reloadAppointments();
                showToastModal({
                    message: statut === 1 ? 'Rendez-vous validé' : 'Rendez-vous annulé',
                    type: 'success'
                });
                loadStats();
            } else {
                showToastModal({ message: json.error || 'Erreur lors de la mise à jour', type: 'error', duration: 3000 });
            }
        } catch {
            showToastModal({ message: 'Erreur réseau lors de la mise à jour', type: 'error', duration: 3000 });
        }
    }

    $(document).on('click', '.btn-validate', function() {
        selectedRdvId = $(this).data('id');
        $('#confirmValidateModal').modal('show');
    });

    $('#confirmValidateBtn').on('click', function() {
        $('#confirmValidateModal').modal('hide');
        updateRdvStatus(selectedRdvId, 1);
    });

    $(document).on('click', '.btn-cancel', function() {
        selectedRdvId = $(this).data('id');
        $('#confirmCancelModal').modal('show');
    });

    $('#confirmCancelBtn').on('click', function() {
        $('#confirmCancelModal').modal('hide');
        updateRdvStatus(selectedRdvId, -2);
    });

    $(document).on('click', '.btn-report', function() {
        selectedRdvId = $(this).data('id');
        const $btn = $(this);
        $('#reportPatient').text($btn.data('patient'));
        $('#reportMedecin').text($btn.data('medecin'));
        $('#reportOldDate').text($btn.data('datecreation'));
        $('#reportNewDate').val($('#date-picker').val());
        $('#reportNewTime').val("09:00");
        $('#reportModal').modal('show');
    });

    $('#confirmReportBtn').on('click', function() {
        const payload = {
            rdv_id: selectedRdvId,
            new_date: $('#reportNewDate').val(),
            new_time: $('#reportNewTime').val()
        };
        $.post(`/api/rdv/${selectedRdvId}/${'report'}`, payload, function(data) {
            if (data.success) {
                showToastModal({ message: 'Rendez-vous reporté', type: 'success' });
                reloadAppointments(); 
                $('#reportModal').modal('hide');
                loadStats();
                
            } else {
                showToastModal({ message: data.error || 'Erreur lors du report', type: 'error', duration: 3000 });
            }
        }).fail(function() {
            showToastModal({ message: 'Erreur réseau lors du report', type: 'error', duration: 3000 });
        });
    });

    $('#prev-date-slider').on('click', function() {
        $('#date-slider').scrollLeft($('#date-slider').scrollLeft() - 100);
    });

    $('#next-date-slider').on('click', function() {
        $('#date-slider').scrollLeft($('#date-slider').scrollLeft() + 100);
    });

    $(document).on('click', '.add-rdv-btn', function() {
        const $cell = $(this).closest('.grid-cell');
        const hour = $cell.data('hour');
        const minute = $cell.data('minute');
        const medecin = $cell.data('medecin');
        $('#rdvTime').val(`${hour.toString().padStart(2, '0')}:${minute}`);
        $('#medecinSelect').val(medecin);
        $('#medecinSelect').prop('readonly', true);
        
        $.getJSON('/api/patients', function(data) {
            const $patientSelect = $('#patientSelect').empty().append('<option value="">Sélectionnez un patient</option>');
            data.forEach(p => $patientSelect.append(`<option value="${p.id}">${p.nom} ${p.prenom}</option>`));
            $('#rdvModal').modal('show');
        }).fail(function() {
            showToastModal({ message: 'Erreur chargement patients', type: 'error', duration: 3000 });
        });
    });

    $('#rdvForm').on('submit', function(e) {
        e.preventDefault();
        const payload = $(this).serializeArray().reduce((o, item) => (o[item.name] = item.value, o), {});
        payload.date = $('#date-picker').val();
        $.post('/api/rdv/create', payload, function(data) {
            if (data.success) {
                $('#rdvModal').modal('hide');
                reloadAppointments();
                loadStats();
                showToastModal({ message: 'Rendez-vous créé', type: 'success' });
            } else {
                showToastModal({ message: data.error || 'Erreur création RDV', type: 'error', duration: 3000 });
            }
        }).fail(function() {
            showToastModal({ message: 'Erreur réseau création RDV', type: 'error', duration: 3000 });
        });
    });

    generateDateSlider();
    updateDateInputs();
    reloadAppointments();
    loadStats();
});
