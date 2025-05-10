$(document).ready(function () {
    let selectedEventId = null;

    const calendarEl = document.getElementById('calendar-holder');
    const contextMenu = $('#calendarContextMenu');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        editable: true,
        locale: 'fr',
        timeZone: 'UTC',
        eventSources: [{
            url: "/api/events/all",
            method: 'GET',
            extraParams: {
                filters: JSON.stringify({})
            },
            eventDataTransform: function (eventData) {
                return {
                    id: eventData.id,
                    title: eventData.title,
                    start: eventData.beginAt,
                    end: eventData.endAt,
                    description: eventData.description,
                    color: eventData.statut === 1 ? '#1cc88a' : undefined
                };
            },
            error: function() {
                showToastModal({
                  message: "Erreur lors du chargement des événements.",
                  type: "error",
                  duration: 3000
                });
            }
        }],
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek',
        },
        eventDidMount: function (info) {
            const statut = info.event.extendedProps.statut;
            const badge = statut === 1
                ? '<span class="badge bg-success">✅ Confirmé</span>'
                : '<span class="badge bg-warning text-dark">⏳ En attente</span>';

            new bootstrap.Tooltip(info.el, {
                title: `
                    <div class='text-start'>
                        <div><strong>${info.event.title}</strong></div>
                        <div>${info.event.extendedProps.description}</div>
                        <div class='mt-1'>${badge}</div>
                    </div>`,
                placement: 'top',
                trigger: 'hover',
                container: 'body',
                html: true
            });

            // Clic droit sur l’événement
            info.el.addEventListener('contextmenu', function (e) {
                e.preventDefault();
                selectedEventId = info.event.id;
                contextMenu
                  .css({ top: e.pageY + 'px', left: e.pageX + 'px' })
                  .show();
            });
        }
    });

    calendar.render();

    // Clic hors du menu contextuel => on le cache
    $(document).click(() => contextMenu.hide());

    // SUPPRESSION
    $('#deleteEventOption').on('click', function () {
        $('#deleteConfirmModal').modal('show');
        contextMenu.hide();
    });

    $('#confirmDeleteBtn').on('click', function () {
        fetch(`/api/event/delete/${selectedEventId}`, { method: 'DELETE' })
        .then(res => res.json())
        .then(data => {
            $('#deleteConfirmModal').modal('hide');
            if (data.success) {
                calendar.refetchEvents();
                showToastModal({
                  message: 'Événement supprimé avec succès.',
                  type: 'success'
                });
            } else {
                showToastModal({
                  message: 'Erreur lors de la suppression de l’événement.',
                  type: 'error',
                  duration: 3000
                });
            }
        })
        .catch(() => {
            $('#deleteConfirmModal').modal('hide');
            showToastModal({
              message: 'Erreur réseau lors de la suppression.',
              type: 'error',
              duration: 3000
            });
        });
    });

    // VALIDATION
    $('#validateEventOption').on('click', function () {
        $('#confirmValidateModal').modal('show');
        contextMenu.hide();
    });

    $('#confirmValidateBtn').on('click', function () {
        fetch(`/api/event/validate/${selectedEventId}`, { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            $('#confirmValidateModal').modal('hide');
            if (data.success) {
                calendar.refetchEvents();
                showToastModal({
                  message: 'Événement validé avec succès.',
                  type: 'success'
                });
            } else {
                showToastModal({
                  message: 'Erreur lors de la validation de l’événement.',
                  type: 'error',
                  duration: 3000
                });
            }
        })
        .catch(() => {
            $('#confirmValidateModal').modal('hide');
            showToastModal({
              message: 'Erreur réseau lors de la validation.',
              type: 'error',
              duration: 3000
            });
        });
    });

    // AJOUT D’ÉVÉNEMENT
    $('#eventForm').on('submit', function (e) {
        e.preventDefault();
        const eventData = {
            beginAt: $('#eventStartDate').val().replace('T', ' ') + ':00',
            endAt: $('#eventEndDate').val()?.replace('T', ' ') || null,
            title: $('#eventTitle').val(),
            description: $('#eventDescription').val(),
        };

        fetch('/api/event/createBooking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(eventData),
        })
        .then(res => res.json())
        .then(data => {
            $('#addEventModal').modal('hide');
            if (data.success) {
                calendar.refetchEvents();
                showToastModal({
                  message: 'Événement ajouté avec succès.',
                  type: 'success'
                });
            } else {
                showToastModal({
                  message: 'Erreur lors de l’ajout de l’événement.',
                  type: 'error',
                  duration: 3000
                });
            }
        })
        .catch(() => {
            $('#addEventModal').modal('hide');
            showToastModal({
              message: 'Erreur réseau lors de l’ajout de l’événement.',
              type: 'error',
              duration: 3000
            });
        });
    });
});
