export const EMPLOYEE_TYPE_INFIRMIER = 'Infirmier';

export const AIDE_SOIGNANT_LABEL = 'Aide soignant(e)';
export const AIDE_SOIGNANT_LABEL_PLURAL = 'Aide(s) soignant(e)s';

export const STAFF_ROLE_ADMIN = 'Admin';
export const STAFF_ROLE_MEDECIN = 'Medecin';
export const STAFF_ROLE_RECEPTIONNISTE = 'Receptionniste';
export const STAFF_ROLE_PATIENT = 'Patient';

export const STAFF_ROLE_OPTIONS = [
    { label: 'Admin', value: STAFF_ROLE_ADMIN },
    { label: 'Médecin', value: STAFF_ROLE_MEDECIN },
    { label: 'Réceptionniste', value: STAFF_ROLE_RECEPTIONNISTE },
    { label: 'Patient', value: STAFF_ROLE_PATIENT }
];

export const STAFF_ROLE_OPTIONS_WITHOUT_PATIENT = STAFF_ROLE_OPTIONS.filter(
    (option) => option.value !== STAFF_ROLE_PATIENT
);

const RECEPTION_ROLES = ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'];

export function formatEmployeeTypeLabel(type) {
    if (type == null || type === '') return type;
    const normalized = String(type).trim();
    if (normalized.toLowerCase().includes('infirmier')) {
        return AIDE_SOIGNANT_LABEL;
    }
    return normalized;
}

export const employeeTypeInfirmierOption = {
    label: AIDE_SOIGNANT_LABEL,
    value: EMPLOYEE_TYPE_INFIRMIER
};

export function resolveRoleFromRoles(roles = []) {
    const list = Array.isArray(roles) ? roles : [];

    if (list.includes('ROLE_ADMIN')) return STAFF_ROLE_ADMIN;
    if (list.includes('ROLE_MEDECIN')) return STAFF_ROLE_MEDECIN;
    if (list.includes('ROLE_PATIENT')) return STAFF_ROLE_PATIENT;
    if (RECEPTION_ROLES.some((role) => list.includes(role))) {
        return STAFF_ROLE_RECEPTIONNISTE;
    }

    return STAFF_ROLE_RECEPTIONNISTE;
}

export function suggestRoleFromEmployeeType(type) {
    const normalized = String(type || '').trim().toLowerCase();

    if (normalized === 'admin') return STAFF_ROLE_ADMIN;
    if (normalized === 'medecin' || normalized === 'médecin') return STAFF_ROLE_MEDECIN;
    if (normalized === 'infirmier' || normalized === 'receptionniste' || normalized === 'reception') {
        return STAFF_ROLE_RECEPTIONNISTE;
    }

    return null;
}

export function formatStaffRoleLabel(role) {
    const option = STAFF_ROLE_OPTIONS.find((item) => item.value === role);
    return option?.label || role || '-';
}
