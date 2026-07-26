const ROLE_ALIASES = {
    ROLE_ADMIN: 'admin',
    ROLE_MEDECIN: 'medecin',
    ROLE_RECEPTION: 'reception',
    admin: 'admin',
    medecin: 'medecin',
    reception: 'reception'
};

export function normalizeUserRoles(roles = []) {
    const source = Array.isArray(roles) ? roles : [roles];

    return source
        .map((role) => ROLE_ALIASES[role] || String(role || '').replace(/^ROLE_/, '').toLowerCase())
        .filter(Boolean);
}

export function isTaskAllowedForRole(task, roles = []) {
    if (!task?.roles?.length) {
        return true;
    }

    const normalizedRoles = normalizeUserRoles(roles);
    return task.roles.some((role) => normalizedRoles.includes(role));
}

export function resolveTaskMockScenario(task, variantId = null, fallbackScenario = 'static') {
    if (variantId && Array.isArray(task?.variants)) {
        const variant = task.variants.find((entry) => entry.id === variantId);
        if (variant?.mockScenario) {
            return variant.mockScenario;
        }
    }

    return task?.mockScenario || fallbackScenario;
}

export function flattenTaskMenuItems(tasks = []) {
    const items = [];

    tasks.forEach((task) => {
        if (Array.isArray(task.variants) && task.variants.length > 0) {
            task.variants.forEach((variant) => {
                items.push({
                    taskId: task.id,
                    variantId: variant.id,
                    label: `${task.label} — ${variant.label}`,
                    icon: task.icon || 'pi pi-circle',
                    description: variant.description || task.description || ''
                });
            });
            return;
        }

        items.push({
            taskId: task.id,
            variantId: null,
            label: task.label,
            icon: task.icon || 'pi pi-circle',
            description: task.description || ''
        });
    });

    return items;
}
