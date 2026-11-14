// Document-scoped lock: only a new application document can release it.
let locked = false;
export function lockForPwaUpdate() {
    locked = true;
}
export function assertPwaRequestsAllowed() {
    if (!locked) return;
    const error = new Error('Mise à jour obligatoire : les requêtes de cette version sont suspendues.');
    error.code = 'PWA_UPDATE_REQUIRED';
    throw error;
}
