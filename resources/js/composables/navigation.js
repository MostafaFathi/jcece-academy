export const navigationByArea = {
    student: [
        { label: 'messaging.title', route: 'student.messages', permissions: ['messaging.view'] },
        { label: 'commerce.cart', route: 'student.cart' },
        { label: 'commerce.myOrders', route: 'student.orders.index' },
        { label: 'nav.overview', route: 'student.dashboard' },
        { label: 'learning.myCourses', route: 'student.courses.index' },
        { label: 'assessments.certificates', route: 'student.certificates.index' },
        { label: 'support.heading', route: 'student.support.index' },
        { label: 'profile.title', route: 'profile' },
    ],
    admin: [
        { label: 'reports.title', route: 'admin.reports', permissions: ['reports.view'] },
        { label: 'nav.overview', route: 'admin.dashboard', roles: ['admin', 'content_manager', 'sales_support'], anyPermission: ['courses.view', 'users.view', 'categories.view', 'instructors.view', 'packages.view', 'reports.view'] },
        { label: 'admin.users', route: 'admin.users.index', permissions: ['users.view'], group: 'rolePermissions.accessGroup' },
        { label: 'admin.instructors', route: 'admin.instructors.index', permissions: ['instructors.view'], group: 'rolePermissions.accessGroup' },
        { label: 'rolePermissions.title', route: 'admin.roles.index', permissions: ['roles.manage'], roles: ['admin'], group: 'rolePermissions.accessGroup' },
        { label: 'admin.categories', route: 'admin.categories.index', permissions: ['categories.view'], group: 'nav.content' },
        { label: 'admin.courses', route: 'admin.courses.index', permissions: ['courses.view'], group: 'nav.content' },
        { label: 'packages.adminHeading', route: 'admin.packages.index', permissions: ['packages.view'], group: 'nav.content' },
        { label: 'audit.heading', route: 'admin.audit.index', roles: ['admin'], group: 'operations.workspace' },
        { label: 'operations.orders', route: 'admin.orders.index', permissions: ['orders.view'], group: 'operations.workspace' },
        { label: 'commerce.coupons', route: 'admin.coupons.index', permissions: ['coupons.view'], group: 'operations.workspace' },
        { label: 'operations.payments', route: 'admin.payments.index', permissions: ['payments.view'], group: 'operations.workspace' },
        { label: 'operations.reviews', route: 'admin.reviews.index', permissions: ['reviews.view'], group: 'operations.workspace' },
        { label: 'operations.certificates', route: 'admin.certificates.index', permissions: ['certificates.view'], group: 'operations.workspace' },
        { label: 'operations.support', route: 'admin.tickets.index', permissions: ['support_tickets.view'], group: 'operations.workspace' },
        { label: 'policies.manage', route: 'admin.policy-pages', permissions: ['policy_pages.view'], group: 'nav.website' },
        { label: 'site.manage', route: 'admin.site-content', permissions: ['site_content.manage'], group: 'nav.website' },
    ],
    instructor: [
        { label: 'messaging.title', route: 'instructor.messages', permissions: ['messaging.view'] },
        { label: 'reports.title', route: 'instructor.reports', roles: ['instructor'], permissions: ['courses.view'] },
        { label: 'instructor.dashboard', route: 'instructor.dashboard', permissions: ['courses.view'] },
        { label: 'instructor.myCourses', route: 'instructor.courses.index', permissions: ['courses.view'] },
    ],
    support: [
        { label: 'nav.overview', route: 'support.dashboard' },
        { label: 'nav.tickets', route: 'support.tickets', permissions: ['support_tickets.view'] },
        { label: 'nav.orders', route: 'support.orders', permissions: ['orders.view'] },
        { label: 'operations.payments', route: 'support.payments.index', permissions: ['payments.view'] },
    ],
};

export function visibleNavigation(items, auth) {
    return items.filter((item) => {
        const hasAllPermissions = !item.permissions || item.permissions.every(auth.can);
        const hasAnyPermission = !item.anyPermission || auth.canAny(item.anyPermission);
        const hasRole = !item.roles || auth.hasAnyRole(item.roles);

        return hasAllPermissions && hasAnyPermission && hasRole;
    });
}
