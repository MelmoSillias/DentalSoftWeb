const LEGACY_LINK_REWRITES = [
    [/^\/reception\/patients\/?$/, '/patients/liste'],
    [/^\/reception\/caisse\/?$/, '/caisse'],
    [/^\/medecin\/agenda\/?$/, '/agenda/rendez-vous'],
    [/^\/admin\/agenda\/jours-conges\/?$/, '/administration/gestionrh'],
    [/^\/admin\/consommables\/?$/, '/administration/consommables'],
    [/^\/admin\/users\/?$/, '/administration/utilisateurs'],
    [/^\/admin\/utilisateurs\/?$/, '/administration/utilisateurs'],
    [/^\/medecin\/consultation\/en-attente\/?$/, '/consultations/cards'],
    [/^\/consultations\/?$/, '/consultations/table'],
    [/^\/consultations\/(\d+)\/?$/, (match) => `/consultations/form?id=${match[1]}&mode=continue`],
    [/^\/medecin\/consultation\/(\d+)\/details\/?$/, (match) => `/consultations/form?id=${match[1]}&mode=continue`]
];


function rewriteLegacyPath(path) {
    for (const [pattern, replacement] of LEGACY_LINK_REWRITES) {
        const match = path.match(pattern);
        if (match) {
            return typeof replacement === 'function' ? replacement(match) : replacement;
        }
    }

    return path;
}

export function normalizeNotificationItem(item) {
    return {
        id: item?.id,
        title: item?.title || 'Notification',
        message: item?.message || '',
        status: item?.status || (item?.read ? 'vu' : 'non_vu'),
        type: item?.type || 'info',
        priority: item?.priority || 'info',
        createdAt: item?.createdAt || item?.date || null,
        link: resolveNotificationLink(item?.link) || null
    };
}

export function resolveNotificationLink(link) {
    if (!link || typeof link !== 'string') {
        return null;
    }

    const trimmed = link.trim();
    if (!trimmed) {
        return null;
    }

    if (/^https?:\/\//i.test(trimmed)) {
        try {
            const url = new URL(trimmed);
            const rewrittenPath = rewriteLegacyPath(url.pathname);
            if (rewrittenPath !== url.pathname) {
                url.pathname = rewrittenPath;
            }
            return url.toString();
        } catch {
            return trimmed;
        }
    }

    const queryIndex = trimmed.indexOf('?');
    const path = queryIndex >= 0 ? trimmed.slice(0, queryIndex) : trimmed;
    const query = queryIndex >= 0 ? trimmed.slice(queryIndex) : '';
    const rewrittenPath = rewriteLegacyPath(path);

    return `${rewrittenPath}${query}`;
}

export function navigateToNotificationLink(router, link) {
    const resolvedLink = resolveNotificationLink(link);
    if (!resolvedLink || !router) {
        return Promise.resolve(false);
    }

    if (/^https?:\/\//i.test(resolvedLink)) {
        window.location.assign(resolvedLink);
        return Promise.resolve(true);
    }

    return router.push(resolvedLink).then(
        () => true,
        () => false
    );
}
