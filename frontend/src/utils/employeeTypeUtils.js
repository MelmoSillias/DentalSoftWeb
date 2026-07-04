export const EMPLOYEE_TYPE_INFIRMIER = 'Infirmier';

export const AIDE_SOIGNANT_LABEL = 'Aide soignant(e)';
export const AIDE_SOIGNANT_LABEL_PLURAL = 'Aide(s) soignant(e)s';

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
