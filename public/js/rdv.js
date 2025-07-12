$(document).ready(function () {
    let currentDate = new Date();
    let selectedRdvId = null;
    let selectedRdvMedecinId = null;

    $('#patientSelect').select2({
        placeholder: 'Rechercher un patient',
        minimumInputLength: 1,
        dropdownParent: $('#rdvModal'),
        ajax: {
            url: '/api/patients/search',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data.results
                };
            },
            cache: true
        },
        width: '100%'
    });

    const calendarEl = document.getElementById('calendar');
    let medecinFilter = document.getElementById('filterMedecin').value;
    let patientSearchQuery = '';

    const calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'fr',
        initialView: 'timeGridWeek',
        height: 900,
        expandRows: true,
        themeSystem: 'bootstrap',
        hiddenDays: [0],
        headerToolbar: {
            left: 'prev today next',
            center: 'title',
            right: 'timeGridWeek',
        },
        slotMinTime: '08:00:00',
        slotMaxTime: '16:00:00',
        nowIndicator: true,
        navLinks: true,
        events: function (fetchInfo, successCallback, failureCallback) {
            document.getElementById('calendarOverlayLoader').style.display = 'block';
            const params = new URLSearchParams({
                start: fetchInfo.startStr,
                end: fetchInfo.endStr
            });
            if (medecinFilter) {
                params.set('medecin', medecinFilter);
            }
            fetch(`/api/rdvs?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    const filtered = data.filter(rdv => {
                        if (!patientSearchQuery) return true;
                        return rdv.patient.toLowerCase().includes(patientSearchQuery.toLowerCase());
                    });

                    const events = filtered.map(rdv => ({
                        id: rdv.id,
                        title: `${rdv.patient} – ${rdv.medecin}`,
                        start: rdv.dateRdv,
                        end: rdv.endDate,
                        extendedProps: {
                            statut: rdv.statut,
                            dateCreation: rdv.dateCreation,
                            reportedAt: rdv.reportedAt,
                            medecin: rdv.medecin,
                            medecin_id: rdv.medecin_id
                        },
                        textColor: 'rgb(31, 31, 31)'
                    }));

                    successCallback(events);
                })
                .catch(() => failureCallback()).finally(() => {
                    document.getElementById('calendarOverlayLoader').style.display = 'none';
                });
        },
        eventClassNames: function (arg) {
            switch (arg.event.extendedProps.statut) {
                case 0: return ['rdv-pending'];
                case 1: return ['rdv-validated'];
                case -1: return ['rdv-postponed'];
                case -2: return ['rdv-cancelled'];
            }
        },
        dateClick: function (info) {
            // ouvre votre modal de création rdv
            const dateStr = info.dateStr.substr(0, 10);
            const timeStr = info.dateStr.substr(11, 5);
            // remplir les champs
            document.querySelector('#rdvModal #medecinSelect').value = info.resource ? info.resource.id : '';
            document.querySelector('#rdvModal #rdvTime').value = timeStr;
            document.querySelector('#rdvModal #rdvDate').value = dateStr;

            $('#rdvModal').modal('show');
        },
        eventDidMount: function (info) {
            const event = info.event;
            const tooltip = `
		                    <strong>${event.title}</strong><br>
		                    <small>Créé le : ${event.extendedProps.dateCreation}</small><br>
		                    ${event.extendedProps.reportedAt ? `<span class="text-warning">Reporté le : ${event.extendedProps.reportedAt}</span>` : ''}
		                `;
            tippy(info.el, {
                content: tooltip,
                allowHTML: true,
                theme: 'light-border',
                placement: 'top'
            });

            // Clic droit = menu contextuel
            info.el.addEventListener('contextmenu', function (e) {
                e.preventDefault();
                if (event.extendedProps.statut !== 0) return;
                window.selectedRdvId = event.id;
                window.selectedRdvMedecin = event.medecin_id || null;
                const menu = document.getElementById('rdvContextMenu');
                menu.style.left = `${e.pageX}px`;
                menu.style.top = `${e.pageY}px`;
                menu.style.display = 'block';
                setTimeout(() => {
                    document.addEventListener('click', closeContextMenu);
                }, 10);
            });
        },
        datesSet: function (info) {
            // recharge statistiques
            const params = new URLSearchParams({
                start: info.startStr,
                end: info.endStr
            });
            if (medecinFilter) params.set('medecin', medecinFilter);

        }
    });

    calendar.render();
    // Filtre médecin : on refait fetch events + stats
    document.getElementById('filterMedecin').addEventListener('change', function () {
        medecinFilter = this.value;
        calendar.refetchEvents();
        calendar.trigger('datesSet', { // simule pour stats
            startStr: calendar.view.activeStartStr,
            endStr: calendar.view.activeEndStr
        });
    });

    document.getElementById('searchPatient').addEventListener('input', function () {
        patientSearchQuery = this.value;
        calendar.refetchEvents(); // recharge avec filtre actif
    });


    $('#rdvForm').on('submit', function (e) {
        e.preventDefault();
        const payload = $(this).serializeArray().reduce((o, item) => (o[item.name] = item.value, o), {});
        payload.date = $('#rdvDate').val();
        $.post('/api/rdv/create', payload, function (data) {
            if (data.success) {
                $('#rdvModal').modal('hide'); calendar.refetchEvents(); // recharge avec filtre actif
                reloadAppointments(); 
                showToastModal({ message: 'Rendez-vous créé', type: 'success' });
            } else {
                showToastModal({ message: data.error || 'Erreur création RDV', type: 'error', duration: 3000 });
            }
        }).fail(function () {
            showToastModal({ message: 'Erreur réseau création RDV', type: 'error', duration: 3000 });
        });
    });

    function closeContextMenu(e = null) {
        const menu = document.getElementById('rdvContextMenu');
        if (!e || !menu.contains(e.target)) {
            menu.style.display = 'none';
            document.removeEventListener('click', closeContextMenu);
        }
    } 
    
    document.getElementById('ctx-validate').addEventListener('click', function () {
        document.getElementById('validateMedecinSelect').value = window.selectedRdvMedecin;
        $('#confirmValidateModal').modal('show');
        closeContextMenu();
    });

    document.getElementById('ctx-cancel').addEventListener('click', function () {
        $('#confirmCancelModal').modal('show');
        closeContextMenu();
    });

    document.getElementById('ctx-report').addEventListener('click', function () {
        // Remplir les infos pour le report
        const evt = calendar.getEventById(window.selectedRdvId);
        $('#reportPatient').text(evt.title);
        $('#reportMedecin').text(window.selectedRdvMedecin);
        $('#reportOldDate').text(evt.extendedProps.dateCreation);
        $('#reportNewDate').val(evt.startStr.substr(0, 10));
        $('#reportNewTime').val(evt.startStr.substr(11, 5));
        $('#reportModal').modal('show');
        closeContextMenu();
    });

    document.getElementById('confirmValidateBtn').addEventListener('click', function () {
        const id = window.selectedRdvId;
        const med = document.getElementById('validateMedecinSelect').value;

        fetch(`/api/rdv/${id}/validate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ medecin: med })
        }).then(r => r.json()).then(json => {
            if (json.success) {
                $('#confirmValidateModal').modal('hide');
                calendar.refetchEvents();
                reloadAppointments(); 
                showToastModal({ message: 'Rendez-vous validé', type: 'success' });
            } else {
                showToastModal({ message: json.error || 'Erreur', type: 'error' });
            }
        }).catch(() => {
            showToastModal({ message: 'Erreur réseau', type: 'error' });
        });
    });

    document.getElementById('confirmCancelBtn').addEventListener('click', function () {
        const id = window.selectedRdvId;
        fetch(`/api/rdv/${id}/cancel`, {
            method: 'POST'
        }).then(r => r.json()).then(json => {
            if (json.success) {
                $('#confirmCancelModal').modal('hide');
                calendar.refetchEvents();
                reloadAppointments(); 
                showToastModal({ message: 'Rendez-vous annulé', type: 'success' });
            } else {
                showToastModal({ message: json.error || 'Erreur', type: 'error' });
            }
        }).catch(() => {
            showToastModal({ message: 'Erreur réseau', type: 'error' });
        });
    });

    document.getElementById('confirmReportBtn').addEventListener('click', function () {
        const id = window.selectedRdvId;
        const payload = {
            new_date: document.getElementById('reportNewDate').value,
            new_time: document.getElementById('reportNewTime').value,
            new_duration: document.getElementById('reportRdvDuration').value,
            new_medecin: document.getElementById('reportValidateMedecinSelect').value
        };

        fetch(`/api/rdv/${id}/report`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(r => r.json()).then(json => {
            if (json.success) {
                $('#reportModal').modal('hide');
                calendar.refetchEvents();
                 reloadAppointments(); 
                showToastModal({ message: 'Rendez-vous reporté', type: 'success' });
            } else {
                showToastModal({ message: json.error || 'Erreur lors du report', type: 'error' });
            }
        }).catch(() => {
            showToastModal({ message: 'Erreur réseau', type: 'error' });
        });
    });
 
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
            $dateItem.on('click', function () {
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

    $('#date-picker').on('change', function () {
        currentDate = new Date($(this).val());
        generateDateSlider();
        reloadAppointments();
        loadStats()
    });

    $('#today-btn').on('click', function () {
        currentDate = new Date();
        generateDateSlider();
        reloadAppointments();
        loadStats()
    });

    function reloadAppointments() {
        const url = `/api/rdvs/${currentDate.toISOString().split('T')[0]}`;
        $('.grid-cell').each(function () {
            const $cell = $(this);
            const $addBtn = $cell.find('.add-rdv-btn').detach();
            $cell.empty().append($addBtn.show());
        });
        $.getJSON(url, function (data) {
            data.forEach(function (rdv) {
                const rdvDate = new Date(rdv.dateRdv);
                const hour = rdvDate.getHours();
                const minutes = rdvDate.getMinutes();
                const slot = minutes < 15 ? '00' : minutes < 30 ? '15' : minutes < 45 ? '30' : '45';
                const $cell = $(`.grid-cell[data-hour="${hour}"][data-minute="${slot}"][data-medecin="${rdv.medecin_id}"]`);
                if ($cell.length) {
                    $cell.find('.add-rdv-btn').hide();
                    const statusClass = rdv.statut === 0 ? 'primary' :
                        rdv.statut === 1 ? 'success' :
                            rdv.statut === -1 ? 'warning' :
                                'danger';
                    const actionButtons = rdv.statut === 0 ? `
                        <div class="rdv-actions mt-4"> 
                            <button class="btn btn-sm btn-success btn-validate" data-id="${rdv.id}" data-medecin="${rdv.medecin_id}"><i class="fas fa-check"></i></button>
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
        }).fail(function () {
            showToastModal({ message: "Erreur de chargement des rendez-vous", type: "error", duration: 3000 });
        });
    }

    function loadStats() {
        const date = currentDate.toISOString().split('T')[0];
        const url = `/api/rdvs/stats/${date}`;

        $.getJSON(url, function (data) {
            $('#stats-pending').text(data.pending);
            $('#stats-validated').text(data.validated);
            $('#stats-postponed').text(data.postponed);
            $('#stats-cancelled').text(data.cancelled);
        }).fail(function () {
            showToastModal({ message: "Erreur de chargement des statistiques", type: "error", duration: 3000 });
        });
    }

    $('.btn-closer').on('click', function () {
        $(this).closest('.modal').modal('hide');
    });

    $(document).on('click', '.btn-validate', function () {
        window.selectedRdvId = $(this).data('id');
        Window.selectedRdvMedecin = $(this).data('medecin'); 
 
        // on pré-sélectionne le médecin d’origine dans le select
        document.getElementById('validateMedecinSelect').value = $(this).data('medecin');
        $('#confirmValidateModal').modal('show');
    });

    $(document).on('click', '.btn-cancel', function () {
        window.selectedRdvId = $(this).data('id');
        $('#confirmCancelModal').modal('show');
    });

    $(document).on('click', '.btn-report', function () {
        window.selectedRdvId = $(this).data('id');
        const $btn = $(this);
        $('#reportPatient').text($btn.data('patient'));
        $('#reportMedecin').text($btn.data('medecin'));
        $('#reportOldDate').text($btn.data('datecreation'));
        $('#reportNewDate').val($('#date-picker').val());
        $('#reportNewTime').val("09:00");
        $('#reportModal').modal('show');
    });


    $('#prev-date-slider').on('click', function () {
        $('#date-slider').scrollLeft($('#date-slider').scrollLeft() - 100);
    });

    $('#next-date-slider').on('click', function () {
        $('#date-slider').scrollLeft($('#date-slider').scrollLeft() + 100);
    });

    $(document).on('click', '.add-rdv-btn', function () {
        const $cell = $(this).closest('.grid-cell');
        const hour = $cell.data('hour');
        const minute = $cell.data('minute');
        const medecin = $cell.data('medecin');
        $('#rdvTime').val(`${hour.toString().padStart(2, '0')}:${minute}`);
        $('#medecinSelect').val(medecin);
        $('#medecinSelect').prop('readonly', true);

        $.getJSON('/api/patients', function (data) {
            const $patientSelect = $('#patientSelect').empty().append('<option value="">Sélectionnez un patient</option>');
            data.forEach(p => $patientSelect.append(`<option value="${p.id}">${p.nom} ${p.prenom}</option>`));
            $('#rdvModal').modal('show');
        }).fail(function () {
            showToastModal({ message: 'Erreur chargement patients', type: 'error', duration: 3000 });
        });
    });

     

    generateDateSlider();
    updateDateInputs();
    reloadAppointments();
    loadStats();
});
