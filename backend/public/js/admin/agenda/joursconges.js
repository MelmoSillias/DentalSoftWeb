document.addEventListener('DOMContentLoaded', async () => {
  const now = new Date();
  const viewStart = new Date(now.getFullYear() - 3, 0, 1);
  const viewEnd = new Date(now.getFullYear() + 2, 11, 31);

  // Ferme tout modal via le bouton .btn-close
  $('.btn-close').on('click', function () {
    $(this).closest('.modal').modal('hide');
  });

  // 📦 Récupération de la config (jours fériés, fermetures hebdso)
  let config;
  try {
    config = await fetch('/api/holidays').then(r => r.json());
  } catch (e) {
    showToastModal({ message: "Impossible de charger la configuration des jours.", type: "error", duration: 3000 });
    return; // On stoppe l'initialisation
  }
  const holidayDates = config.feries;
  const closedWeekDays = config.fermeturesHebdo;

  // 📊 Préparation des datasets
  const groupsDS = new vis.DataSet();
  const itemsDS = new vis.DataSet();
  const backgroundDS = new vis.DataSet();

  function getClassForType(type) {
    switch (type.toLowerCase()) {
      case 'vacances':
        return 'conge-annuel';
      case 'teletravail':
        return 'conge-autre';
      case 'arret':
        return 'conge-maladie';
      case 'deplacement':
        return 'conge-autre';
      default:
        return 'conge-autre';
    }
  }

  // 👥 Récupération des employés + congés
  let data;
  try {
    data = await fetch('/api/conges/employees').then(r => r.json());
  } catch (e) {
    showToastModal({ message: "Erreur lors de la récupération des congés.", type: "error", duration: 3000 });
    return;
  }

  data.forEach(emp => {
    groupsDS.add({ id: emp.id, content: `${emp.prenom} ${emp.nom}` });
    emp.conges.forEach(c => {
      itemsDS.add({
        id: c.id,
        group: emp.id,
        content: c.type,
        start: c.start,
        end: new Date(new Date(c.end).getTime() + 86400000).toISOString().slice(0, 10),
        className: getClassForType(c.type)
      });
    });
    // Remplissage du select
    const opt = document.createElement('option');
    opt.value = emp.id;
    opt.textContent = emp.prenom + ' ' + emp.nom;
    document.getElementById('employeeSelect').append(opt);
  });

  function updateBackground() {
    backgroundDS.clear();
    // Jours fériés
    holidayDates.forEach(date => {
      backgroundDS.add({
        id: `ferie-${date}`,
        content: '',
        start: date,
        end: new Date(new Date(date).getTime() + 86400000).toISOString().slice(0, 10),
        type: 'background',
        className: 'bg-jour-ferie'
      });
    });
    // Fermetures hebdo
    for (let d = new Date(viewStart); d <= viewEnd; d.setDate(d.getDate() + 1)) {
      const iso = d.toISOString().slice(0, 10);
      if (closedWeekDays.includes(d.getDay())) {
        backgroundDS.add({
          id: `closed-${iso}`,
          content: '',
          start: iso,
          end: new Date(new Date(iso).getTime() + 86400000).toISOString().slice(0, 10),
          type: 'background',
          className: 'bg-jour-fermeture'
        });
      }
    }
  }
  updateBackground();

  // 🗓 Initialisation de la timeline
  const timeline = new vis.Timeline(document.getElementById('timeline'), new vis.DataSet([
    ...itemsDS.get(),
    ...backgroundDS.get()
  ]), groupsDS, {
    start: viewStart,
    end: viewEnd,
    stack: false,
    orientation: {
      axis: 'top',
      item: 'top'
    },
    groupOrder: 'content',
    groupHeightMode: 'fixed',
    zoomKey: 'ctrlKey',
    zoomable: true,
    zoomMax: 1000 * 60 * 60 * 24 * 30,
    editable: false,
    groupTemplate: g => g.content
  });

  // Zoom sur le mois courant
  const moisDebut = new Date(now.getFullYear(), now.getMonth(), 1);
  const moisFin = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  timeline.setWindow(moisDebut, moisFin, { animation: true });

  // 🌞 Style de l’axe pour fériés / fermetures
  function styleAxis() {
    document.querySelectorAll('.vis-time-axis .vis-grid').forEach(cell => {
      const dateStr = cell.getAttribute('title') || cell.dataset.date;
      if (!dateStr)
        return;

      const d = new Date(dateStr);
      const iso = d.toISOString().slice(0, 10);
      cell.classList.remove('vis-ferie', 'vis-weekend');
      if (holidayDates.includes(iso))
        cell.classList.add('vis-ferie');
      else if (closedWeekDays.includes(d.getDay()))
        cell.classList.add('vis-weekend');

    });
  }
  timeline.on('rangechanged', styleAxis);
  styleAxis();

  function refreshTimelineItems() {
    const merged = new vis.DataSet();
    itemsDS.get().forEach(i => merged.add(i));
    backgroundDS.get().forEach(b => merged.add(b));
    timeline.setItems(merged);
  }

  // 🎛 Boutons et filtres
  $('#todayBtn').on('click', () => timeline.moveTo(new Date()));
  $('#searchInput').on('input', e => {
    const txt = e.target.value.toLowerCase();
    const filtres = groupsDS.get({
      filter: g => g.content.toLowerCase().includes(txt)
    });
    timeline.setGroups(new vis.DataSet(filtres));
  });

  // --- AJOUT DE CONGÉ ---
  $('#addCongeBtn').on('click', () => {
    const todayISO = new Date().toISOString().slice(0, 10);
    const dispo = data.filter(emp => !emp.conges.some(c => c.type.toLowerCase() === 'vacances' && new Date(c.end).toISOString().slice(0, 10) >= todayISO));
    const $sel = $('#employeeSelect').empty().append('<option disabled selected value="">-- Choisir un employé --</option>');
    if (dispo.length) {
      dispo.forEach(emp => $sel.append(`<option value="${emp.id}">${emp.prenom} ${emp.nom}</option>`));
    } else {
      $sel.append('<option disabled>Aucun employé disponible</option>');
    }
    $('#addCongeModal').modal('show');
  });

  $('#saveCongeBtn').on('click', async () => {
    const emp = +$('#employeeSelect').val();
    const type = $('#typeConge').val();
    const start = $('#startDate').val();
    const end = $('#endDate').val();
    const $card = $('#addCongeModal .modal-content'); 

    if (!emp || !type || !start || !end) {
      showToastModal({ message: 'Tous les champs sont requis', type: 'warning' });
      return;
    }

    const sd = new Date(start),
      ed = new Date(end);
    const overlap = data.find(e => e.id === emp)?.conges.some(c => {
      const cs = new Date(c.start),
        ce = new Date(c.end);
      return sd <= ce && ed >= cs;
    });

    if (overlap) {
      $card.addClass('border border-danger');
      const tip = new bootstrap.Tooltip($card[0], {
        title: "Chevauchement détecté pour cet employé",
        placement: 'top',
        trigger: 'manual'
      });
      tip.show();
      return;
    }

    // Envoi
    let json;
    try {
      const res = await fetch('/api/conges', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ employeId: emp, type, startDate: start, endDate: end })
      });
      json = await res.json();
    } catch {
      showToastModal({ message: 'Erreur réseau lors de l’ajout', type: 'error', duration: 3000 });
      return;
    }

    if (json.conge) {
      itemsDS.add({
        id: json.conge.id,
        group: emp,
        content: type,
        start: json.conge.start,
        end: new Date(new Date(json.conge.end).getTime() + 86400000).toISOString().slice(0, 10),
        className: getClassForType(type)
      });
      refreshTimelineItems();
      styleAxis();
      $('#addCongeModal').modal('hide');
      showToastModal({ message: 'Congé ajouté avec succès', type: 'success' });
    } else {
      showToastModal({ message: 'Échec de l’ajout du congé', type: 'error', duration: 3000 });
    }
  });

  // --- CONFIGURER LES FERMETURES HEBDO ---
  $('#configFermesBtn').on('click', () => {
    $('#configFermesModal').modal('show');
    $('input[name="weekDays"]').each(function () {
      this.checked = closedWeekDays.includes(+this.value);
    });
  });

  $('#addHolidayBtn').on('click', async () => {
    console.log('Adding holiday');
    const d = $('#newHolidayDate').val();
    if (!d || holidayDates.includes(d))
      return;

    try {
      const j = await fetch('/api/holidays', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ date: d })
      }).then(r => r.json());
      if (j.success) {
        holidayDates.push(d);
        updateBackground();
        refreshTimelineItems();
        styleAxis();
        refreshHolidaysUI();
        showToastModal({ message: 'Jour férié ajouté', type: 'success' });
      } else {
        showToastModal({ message: 'Impossible d’ajouter ce jour férié', type: 'error', duration: 3000 });
      }
    } catch {
      showToastModal({ message: 'Erreur réseau lors de l’ajout du jour férié', type: 'error', duration: 3000 });
    }
  });

  $('#saveConfigFermesBtn').on('click', async () => {
    const jours = $('input[name="weekDays"]:checked').map(function () {
      return +this.value
    }).get();
    try {
      await fetch('/api/holidays/closures', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ jours })
      });
      closedWeekDays.splice(0, closedWeekDays.length, ...jours);
      updateBackground();
      refreshTimelineItems();
      styleAxis();
      $('#configFermesModal').modal('hide');
      showToastModal({ message: 'Configuration enregistrée', type: 'success' });
    } catch {
      showToastModal({ message: 'Erreur lors de l’enregistrement', type: 'error', duration: 3000 });
    }
  });

  function refreshHolidaysUI() {
    const ul = $('#holidaysList').empty();
    holidayDates.forEach(d => {
      const li = $(`
        <li class="list-group-item d-flex justify-content-between align-items-center">
          ${d}
          <button type="button" class="close">&times;</button>
        </li>`);
      li.find('.close').on('click', () => {
        holidayDates.splice(holidayDates.indexOf(d), 1);
        updateBackground();
        refreshTimelineItems();
        styleAxis();
        refreshHolidaysUI();
        showToastModal({ message: 'Jour férié supprimé', type: 'info' });
      });
      ul.append(li);
    });
  }

  refreshHolidaysUI(); 
  setTimeout(() => { timeline.moveTo(new Date()) }, 1000);
});