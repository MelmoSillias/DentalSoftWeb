let adminTourMockEnabled = false;
let adminTourMockState = buildSeedState();

function cloneValue(value) {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
}

function buildSeedState() {
    return {
        consumables: [
            { id: 301, nom: 'Gants stériles', fournisseur: 'Medisup', quantity: 120, lowValue: 40, price: 250, deleteToken: 'mock-301' },
            { id: 302, nom: 'Masques chirurgicaux', fournisseur: 'OrthoCare', quantity: 32, lowValue: 30, price: 150, deleteToken: 'mock-302' },
            { id: 303, nom: 'Sérum physiologique', fournisseur: 'PharmaPlus', quantity: 0, lowValue: 12, price: 1200, deleteToken: 'mock-303' },
            { id: 304, nom: 'Aiguilles anesthésie', fournisseur: 'DentaTech', quantity: 18, lowValue: 25, price: 500, deleteToken: 'mock-304' }
        ],
        stockVariations: [
            { id: 401, date: '2026-04-01', consommable: 'Gants stériles', employe: 'Rokhaya Ba', quantiteUtilisee: 20, quantite: 20, type: 'Entrée', mouvement: 'Entrée', description: 'Réapprovisionnement mensuel', consumableId: 301 },
            { id: 402, date: '2026-04-02', consommable: 'Masques chirurgicaux', employe: 'Malick Diop', quantiteUtilisee: 8, quantite: 8, type: 'Sortie', mouvement: 'Sortie', description: 'Distribution bloc 2', consumableId: 302 },
            { id: 403, date: '2026-04-02', consommable: 'Sérum physiologique', employe: 'Rokhaya Ba', quantiteUtilisee: 12, quantite: 12, type: 'Sortie', mouvement: 'Sortie', description: 'Usage urgence', consumableId: 303 },
            { id: 404, date: '2026-04-03', consommable: 'Aiguilles anesthésie', employe: 'Mamadou Seck', quantiteUtilisee: 5, quantite: 5, type: 'Entrée', mouvement: 'Entrée', description: 'Commande express', consumableId: 304 }
        ],
        employees: [
            {
                id: 11,
                nom: 'Fall',
                prenom: 'Aissatou',
                fullname: 'Fall Aissatou',
                type: 'Medecin',
                fonction: 'Chirurgien-dentiste',
                telephone: '+221770001111',
                email: 'a.fall@dentalsoft.local',
                dateEmbauche: '2025-01-15',
                typeContrat: 'CDI',
                dureeContrat: null,
                typeSalaire: 'pourcentage',
                valeurSalaire: 35,
                comingDays: ['Lundi', 'Mardi', 'Jeudi', 'Vendredi']
            },
            {
                id: 12,
                nom: 'Diallo',
                prenom: 'Aminata',
                fullname: 'Diallo Aminata',
                type: 'Receptionniste',
                fonction: 'Accueil et admission',
                telephone: '+221770002222',
                email: 'a.diallo@dentalsoft.local',
                dateEmbauche: '2025-06-03',
                typeContrat: 'CDD',
                dureeContrat: 12,
                typeSalaire: 'fixe',
                valeurSalaire: 180000,
                comingDays: ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi']
            },
            {
                id: 13,
                nom: 'Ka',
                prenom: 'Fatou',
                fullname: 'Ka Fatou',
                type: 'Admin',
                fonction: 'Administration RH',
                telephone: '+221770003333',
                email: 'f.ka@dentalsoft.local',
                dateEmbauche: '2024-11-20',
                typeContrat: 'CDI',
                dureeContrat: null,
                typeSalaire: 'fixe',
                valeurSalaire: 220000,
                comingDays: ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi']
            },
            {
                id: 14,
                nom: 'Sy',
                prenom: 'Moussa',
                fullname: 'Sy Moussa',
                type: 'Autre',
                fonction: 'Assistant logistique',
                telephone: '+221770004444',
                email: 'm.sy@dentalsoft.local',
                dateEmbauche: '2026-02-10',
                typeContrat: 'Stage',
                dureeContrat: 6,
                typeSalaire: 'non_defini',
                valeurSalaire: null,
                comingDays: ['Lundi', 'Mercredi', 'Vendredi']
            }
        ],
        salles: [
            { id: 501, nom: 'Salle A', description: 'Consultation générale', statut: 'disponible', type: 'Consultation' },
            { id: 502, nom: 'Salle B', description: 'Chirurgie légère', statut: 'occupé', type: 'Chirurgie' },
            { id: 503, nom: 'Salle C', description: 'Radiologie panoramique', statut: 'maintenance', type: 'Radiologie' },
            { id: 504, nom: 'Salle D', description: 'Consultation pédiatrique', statut: 'disponible', type: 'Consultation' }
        ],
        users: [
            { id: 701, username: 'aminata.diallo', type: 'ROLE_RECEPTION', fonction: 'Réception', employee: { nom: 'Diallo', prenom: 'Aminata' } },
            { id: 702, username: 'dr.seck', type: 'ROLE_MEDECIN', fonction: 'Médecin', employee: { nom: 'Seck', prenom: 'Mamadou' } },
            { id: 703, username: 'rokhaya.ba', type: 'ROLE_INFIRMIER', fonction: 'Infirmier', employee: { nom: 'Ba', prenom: 'Rokhaya' } },
            { id: 704, username: 'admin.ka', type: 'ROLE_ADMIN', fonction: 'Administration', employee: { nom: 'Ka', prenom: 'Fatou' } }
        ],
        sentNotifications: []
    };
}

export function isAdminTourMockEnabled() {
    return adminTourMockEnabled;
}

export function activateAdminTourMock() {
    adminTourMockState = buildSeedState();
    adminTourMockEnabled = true;
    return cloneValue(adminTourMockState);
}

export function resetAdminTourMockData() {
    adminTourMockState = buildSeedState();
    return cloneValue(adminTourMockState);
}

export function deactivateAdminTourMock() {
    adminTourMockEnabled = false;
    adminTourMockState = buildSeedState();
}

export function fetchConsumablesTourMock() {
    return cloneValue(adminTourMockState.consumables);
}

export function getConsumableTourMock(consumableId) {
    return cloneValue(adminTourMockState.consumables.find((item) => Number(item.id) === Number(consumableId)) || null);
}

export function addConsumableTourMock(consumable) {
    const nextId = Math.max(0, ...adminTourMockState.consumables.map((item) => Number(item.id) || 0)) + 1;
    const created = {
        id: nextId,
        nom: consumable?.nom || 'Consommable démo',
        fournisseur: consumable?.fournisseur || 'Fournisseur démo',
        quantity: Number(consumable?.quantite ?? consumable?.quantity ?? 0),
        lowValue: Number(consumable?.lowValue ?? 0),
        price: Number(consumable?.price ?? 0),
        deleteToken: `mock-${nextId}`
    };
    adminTourMockState.consumables.unshift(created);
    return { ok: true, data: cloneValue(created) };
}

export function editConsumableTourMock(id, updates) {
    const index = adminTourMockState.consumables.findIndex((item) => Number(item.id) === Number(id));
    if (index === -1) return { ok: false };
    adminTourMockState.consumables[index] = {
        ...adminTourMockState.consumables[index],
        ...updates,
        quantity: Number(updates?.quantite ?? updates?.quantity ?? adminTourMockState.consumables[index].quantity),
        lowValue: Number(updates?.lowValue ?? adminTourMockState.consumables[index].lowValue)
    };
    return { ok: true, data: cloneValue(adminTourMockState.consumables[index]) };
}

export function addStockTourMock(consumableId, values = {}) {
    const item = adminTourMockState.consumables.find((row) => Number(row.id) === Number(consumableId));
    if (!item) return { ok: false };
    item.quantity += Number(values?.quantite ?? 0);
    adminTourMockState.stockVariations.unshift({
        id: Date.now(),
        date: new Date().toISOString().slice(0, 10),
        consommable: item.nom,
        employe: 'Réception démo',
        quantiteUtilisee: Number(values?.quantite ?? 0),
        quantite: Number(values?.quantite ?? 0),
        type: 'Entrée',
        mouvement: 'Entrée',
        description: values?.description || 'Ajout de stock pendant le tour',
        consumableId: item.id
    });
    return { ok: true, data: cloneValue(item) };
}

export function withdrawStockTourMock(consumableId, values = {}) {
    const item = adminTourMockState.consumables.find((row) => Number(row.id) === Number(consumableId));
    if (!item) return { ok: false };
    item.quantity = Math.max(0, item.quantity - Number(values?.quantite ?? 0));
    const employe = adminTourMockState.employees.find((row) => Number(row.id) === Number(values?.employe));
    adminTourMockState.stockVariations.unshift({
        id: Date.now(),
        date: new Date().toISOString().slice(0, 10),
        consommable: item.nom,
        employe: employe?.fullname || 'Employé démo',
        quantiteUtilisee: Number(values?.quantite ?? 0),
        quantite: Number(values?.quantite ?? 0),
        type: 'Sortie',
        mouvement: 'Sortie',
        description: values?.description || 'Retrait de stock pendant le tour',
        consumableId: item.id
    });
    return { ok: true, data: cloneValue(item) };
}

export function deleteConsumableTourMock(consumableId) {
    adminTourMockState.consumables = adminTourMockState.consumables.filter((item) => Number(item.id) !== Number(consumableId));
    return { ok: true };
}

export function fetchStockVariationsTourMock(consumableId = null, start = '', end = '') {
    const startDate = start instanceof Date ? start.toISOString().slice(0, 10) : String(start || '').slice(0, 10);
    const endDate = end instanceof Date ? end.toISOString().slice(0, 10) : String(end || '').slice(0, 10);
    return cloneValue(adminTourMockState.stockVariations.filter((item) => {
        const itemDate = String(item.date || '').slice(0, 10);
        const consumableOk = consumableId ? Number(item.consumableId) === Number(consumableId) : true;
        const startOk = startDate ? itemDate >= startDate : true;
        const endOk = endDate ? itemDate <= endDate : true;
        return consumableOk && startOk && endOk;
    }));
}

export function fetchEmployeesTourMock() {
    return {
        data: cloneValue(adminTourMockState.employees),
        recordsFiltered: adminTourMockState.employees.length,
        recordsTotal: adminTourMockState.employees.length
    };
}

export function fetchSallesTourMock() {
    return cloneValue(adminTourMockState.salles);
}

export function addSalleTourMock(data) {
    const nextId = Math.max(0, ...adminTourMockState.salles.map((item) => Number(item.id) || 0)) + 1;
    const salle = {
        id: nextId,
        nom: data?.nom || 'Salle démo',
        description: data?.description || '',
        statut: 'disponible',
        type: 'Consultation',
        label: data?.nom || 'Salle démo'
    };
    adminTourMockState.salles.unshift(salle);
    return { salle: cloneValue(salle) };
}

export function editSalleTourMock(id, data) {
    const index = adminTourMockState.salles.findIndex((item) => Number(item.id) === Number(id));
    if (index === -1) throw new Error('Salle introuvable');
    adminTourMockState.salles[index] = {
        ...adminTourMockState.salles[index],
        ...data,
        label: data?.nom || adminTourMockState.salles[index].nom
    };
    return { salle: cloneValue(adminTourMockState.salles[index]) };
}

export function deleteSalleTourMock(id) {
    adminTourMockState.salles = adminTourMockState.salles.filter((item) => Number(item.id) !== Number(id));
    return { ok: true };
}

export function fetchUsersTourMock() {
    return {
        data: cloneValue(adminTourMockState.users),
        recordsFiltered: adminTourMockState.users.length,
        recordsTotal: adminTourMockState.users.length
    };
}

export function sendNotificationTourMock(payload = {}) {
    const sent = Array.isArray(payload.recipients) ? payload.recipients.length : 0;
    adminTourMockState.sentNotifications.push({
        id: Date.now(),
        ...cloneValue(payload),
        sent
    });
    return { success: true, sent };
}