export const navigationByArea = {
    student: [
        { label: 'commerce.cart', route: 'student.cart' },
        { label: 'commerce.myOrders', route: 'student.orders.index' },
        { label: 'nav.overview', route: 'student.dashboard' },
        { label: 'learning.myCourses', route: 'student.courses.index' },
        { label: 'assessments.certificates', route: 'student.certificates.index' },
        { label: 'support.heading', route: 'student.support.index' },
    ],
    admin: [
        { label: 'nav.overview', route: 'admin.dashboard', anyPermission: ['courses.view', 'users.view', 'categories.view', 'instructors.view', 'packages.view'] },
        { label: 'admin.categories', route: 'admin.categories.index', permissions: ['categories.view'] },
        { label: 'admin.courses', route: 'admin.courses.index', permissions: ['courses.view'] },
        { label: 'packages.adminHeading', route: 'admin.packages.index', permissions: ['packages.view'] },
        { label: 'admin.users', route: 'admin.users.index', permissions: ['users.view'] },
        { label: 'admin.instructors', route: 'admin.instructors.index', permissions: ['instructors.view'] },
        { label: 'operations.orders', route: 'admin.orders.index', permissions: ['orders.view'] },
        { label: 'operations.payments', route: 'admin.payments.index', permissions: ['payments.view'] },
        { label: 'operations.reviews', route: 'admin.reviews.index', permissions: ['reviews.view'] },
        { label: 'operations.certificates', route: 'admin.certificates.index', permissions: ['certificates.view'] },
        { label: 'operations.support', route: 'admin.tickets.index', permissions: ['support_tickets.view'] },
    ],
    instructor: [
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
