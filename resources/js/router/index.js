import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { createAccessGuard } from './access';

const PublicLayout = () => import('../layouts/PublicLayout.vue');
const AuthLayout = () => import('../layouts/AuthLayout.vue');
const StudentLayout = () => import('../layouts/StudentLayout.vue');
const AdminLayout = () => import('../layouts/AdminLayout.vue');
const InstructorLayout = () => import('../layouts/InstructorLayout.vue');
const SalesSupportLayout = () => import('../layouts/SalesSupportLayout.vue');
const DashboardPage = () => import('../pages/DashboardPage.vue');
const UpcomingPage = () => import('../pages/UpcomingPage.vue');

export const routes = [
    {
        path: '/',
        component: PublicLayout,
        children: [
            { path: '', name: 'home', component: () => import('../pages/HomePage.vue'), meta: { title: 'common.home' } },
            { path: 'courses', name: 'courses.index', component: () => import('../pages/CourseCatalogPage.vue'), meta: { title: 'nav.courses' } },
            { path: 'courses/:slug', name: 'courses.show', component: () => import('../pages/CourseDetailPage.vue'), props: true, meta: { title: 'nav.courses' } },
            { path: 'packages', name: 'packages.index', component: () => import('../pages/PackageCatalogPage.vue'), meta: { title: 'nav.packages' } },
            { path: 'packages/:slug', name: 'packages.show', component: () => import('../pages/PackageDetailPage.vue'), props: true, meta: { title: 'nav.packages' } },
        ],
    },
    {
        path: '/login',
        component: AuthLayout,
        meta: { guestOnly: true },
        children: [{ path: '', name: 'login', component: () => import('../pages/LoginPage.vue'), meta: { title: 'common.login', guestOnly: true } }],
    },
    {
        path: '/student',
        component: StudentLayout,
        meta: { requiresAuth: true, roles: ['student'] },
        children: [
            { path: '', name: 'student.dashboard', component: DashboardPage, meta: { requiresAuth: true, roles: ['student'], title: 'pages.student' } },
            { path: 'learning', name: 'student.learning', component: UpcomingPage, props: { titleKey: 'nav.learning' }, meta: { requiresAuth: true, roles: ['student'], title: 'nav.learning' } },
        ],
    },
    {
        path: '/admin',
        component: AdminLayout,
        meta: { requiresAuth: true, anyPermission: ['courses.view', 'users.view', 'categories.view'] },
        children: [
            { path: '', name: 'admin.dashboard', component: DashboardPage, meta: { requiresAuth: true, anyPermission: ['courses.view', 'users.view', 'categories.view'], title: 'pages.admin' } },
            { path: 'content', name: 'admin.content', component: UpcomingPage, props: { titleKey: 'nav.content' }, meta: { requiresAuth: true, anyPermission: ['courses.view', 'categories.view'], title: 'nav.content' } },
        ],
    },
    {
        path: '/instructor',
        component: InstructorLayout,
        meta: { requiresAuth: true, roles: ['instructor'] },
        children: [
            { path: '', name: 'instructor.dashboard', component: DashboardPage, meta: { requiresAuth: true, roles: ['instructor'], title: 'pages.instructor' } },
            { path: 'assignments', name: 'instructor.assignments', component: UpcomingPage, props: { titleKey: 'nav.assignments' }, meta: { requiresAuth: true, roles: ['instructor'], permissions: ['assignment_submissions.view'], title: 'nav.assignments' } },
        ],
    },
    {
        path: '/sales-support',
        component: SalesSupportLayout,
        meta: { requiresAuth: true, anyPermission: ['support_tickets.view', 'orders.view', 'payments.view'] },
        children: [
            { path: '', name: 'support.dashboard', component: DashboardPage, meta: { requiresAuth: true, anyPermission: ['support_tickets.view', 'orders.view', 'payments.view'], title: 'pages.support' } },
            { path: 'tickets', name: 'support.tickets', component: UpcomingPage, props: { titleKey: 'nav.tickets' }, meta: { requiresAuth: true, permissions: ['support_tickets.view'], title: 'nav.tickets' } },
            { path: 'orders', name: 'support.orders', component: UpcomingPage, props: { titleKey: 'nav.orders' }, meta: { requiresAuth: true, permissions: ['orders.view'], title: 'nav.orders' } },
        ],
    },
    { path: '/dashboard', name: 'dashboard', component: () => import('../pages/DashboardRedirectPage.vue'), meta: { requiresAuth: true } },
    { path: '/403', name: 'forbidden', component: () => import('../pages/UnauthorizedPage.vue'), meta: { title: 'errors.forbiddenTitle' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../pages/NotFoundPage.vue'), meta: { title: 'errors.notFoundTitle' } },
];

const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: () => ({ top: 0 }) });

router.beforeEach((to) => createAccessGuard(useAuthStore())(to));

export default router;
