export function createPortalGuard({ getAuth, getPortal }) {
    return async (to) => {
        const auth = getAuth();
        const portal = getPortal();
        await auth.hydrate();
        await portal.load();
        if (portal.isPortalClosed && to.meta.requiresAuth) return { name: 'portal-closed' };
        if (to.meta.requiresAuth && !auth.isAuthenticated) return { name: 'login', query: { redirect: to.fullPath } };
        if (to.name === 'login' && auth.isAuthenticated) return { name: portal.isPortalClosed ? 'portal-closed' : 'dashboard' };
        return true;
    };
}
