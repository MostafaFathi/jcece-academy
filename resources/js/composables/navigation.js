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
        { label: 'nav.overview', route: 'admin.dashboard' },
        { label: 'admin.categories', route: 'admin.categories.index', permissions: ['categories.view'] },
        { label: 'admin.courses', route: 'admin.courses.index', permissions: ['courses.view'] },
        { label: 'admin.users', route: 'admin.users.index', permissions: ['users.view'] },
        { label: 'admin.instructors', route: 'admin.instructors.index', permissions: ['instructors.view'] },
    ],
    instructor: [
        { label: 'nav.overview', route: 'instructor.dashboard' },
        { label: 'nav.assignments', route: 'instructor.assignments', permissions: ['assignment_submissions.view'] },
    ],
    support: [
        { label: 'nav.overview', route: 'support.dashboard' },
        { label: 'nav.tickets', route: 'support.tickets', permissions: ['support_tickets.view'] },
        { label: 'nav.orders', route: 'support.orders', permissions: ['orders.view'] },
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
