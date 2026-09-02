export const SECRETARY_ROLES = ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'];

export const isAdminUser = (roles = []) => Array.isArray(roles) && roles.includes('ROLE_ADMIN');

export const isSecretaryUser = (roles = []) => Array.isArray(roles) && SECRETARY_ROLES.some((role) => roles.includes(role));

export const canUserModifyInvoice = (user, settings = {}) => {
    const roles = user?.roles ?? [];
    if (isAdminUser(roles)) {
        return true;
    }

    if (isSecretaryUser(roles)) {
        return settings.allowReceptionInvoiceModification === true;
    }

    return false;
};
