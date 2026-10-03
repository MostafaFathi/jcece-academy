export function canAccessRoute(auth, meta = {}) {
    if (meta.roles?.length && !auth.hasAnyRole(meta.roles)) {
        return false;
    }

    if (meta.permissions?.length && !meta.permissions.every(auth.can)) {
        return false;
    }

    if (meta.anyPermission?.length && !auth.canAny(meta.anyPermission)) {
        return false;
    }

    return true;
}

export function homeRouteFor(auth) {
    if (auth.hasRole('admin') || auth.hasRole('content_manager')) {
        return { name: 'admin.dashboard' };
    }

    if (auth.hasRole('instructor')) {
        return { name: 'instructor.dashboard' };
    }

    if (auth.hasRole('sales_support')) {
        return { name: 'support.dashboard' };
    }

    return { name: 'student.dashboard' };
}

export function createAccessGuard(auth) {
    return async (to) => {
        try {
            await auth.initialize();
        } catch {
            if (to.name !== 'home' && to.name !== 'login') {
                return { name: 'home', query: { startup: 'failed' } };
            }
        }

        if (to.meta.guestOnly && auth.isAuthenticated) {
            return homeRouteFor(auth);
        }

        if (to.meta.requiresAuth && !auth.isAuthenticated) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        if (to.path?.startsWith('/admin') && auth.hasRole?.('instructor') && !auth.hasAnyRole(['admin', 'content_manager'])) {
            return { name: 'forbidden' };
        }

        if (auth.isAuthenticated && !canAccessRoute(auth, to.meta)) {
            return { name: 'forbidden' };
        }

        return true;
    };
}
