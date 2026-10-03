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
            { path: 'certificates/verify/:token', name: 'certificates.verify', component: () => import('../pages/CertificateVerificationPage.vue'), props: true, meta: { title: 'assessments.verify' } },
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
            { path: 'cart', name: 'student.cart', component: () => import('../pages/CartPage.vue'), meta: { requiresAuth: true, roles: [], title: 'commerce.cart' } },
            { path: 'checkout', name: 'student.checkout', component: () => import('../pages/CheckoutPage.vue'), meta: { requiresAuth: true, roles: [], title: 'commerce.checkout' } },
            { path: 'orders', name: 'student.orders.index', component: () => import('../pages/OrdersPage.vue'), meta: { requiresAuth: true, roles: [], title: 'commerce.myOrders' } },
            { path: 'orders/:id', name: 'student.orders.show', component: () => import('../pages/OrderDetailPage.vue'), props: true, meta: { requiresAuth: true, roles: [], title: 'commerce.orderDetails' } },
            { path: 'courses', name: 'student.courses.index', component: () => import('../pages/MyCoursesPage.vue'), meta: { requiresAuth: true, roles: [], title: 'learning.myCourses' } },
            { path: 'quizzes/:id', name: 'student.quizzes.show', component: () => import('../pages/QuizPage.vue'), props: true, meta: { requiresAuth: true, roles: [], title: 'assessments.quiz' } },
            { path: 'assignments/:id', name: 'student.assignments.show', component: () => import('../pages/AssignmentPage.vue'), props: true, meta: { requiresAuth: true, roles: [], title: 'assessments.assignment' } },
            { path: 'certificates', name: 'student.certificates.index', component: () => import('../pages/CertificatesPage.vue'), meta: { requiresAuth: true, roles: [], title: 'assessments.certificates' } },
            { path: 'support', name: 'student.support.index', component: () => import('../pages/SupportTicketsPage.vue'), meta: { requiresAuth: true, roles: [], title: 'support.heading' } },
            { path: 'support/new', name: 'student.support.create', component: () => import('../pages/CreateSupportTicketPage.vue'), meta: { requiresAuth: true, roles: [], title: 'support.createHeading' } },
            { path: 'support/:id', name: 'student.support.show', component: () => import('../pages/SupportTicketDetailPage.vue'), props: true, meta: { requiresAuth: true, roles: [], title: 'support.ticket' } },
            { path: 'learning', name: 'student.learning', redirect: { name: 'student.courses.index' } },
        ],
    },
    {
        path: '/learn',
        component: StudentLayout,
        meta: { requiresAuth: true, roles: [] },
        children: [{ path: 'courses/:slug', name: 'student.courses.learn', component: () => import('../pages/CourseLearningPage.vue'), props: true, meta: { requiresAuth: true, roles: [], title: 'learning.continue' } }],
    },
    {
        path: '/admin',
        component: AdminLayout,
        meta: { requiresAuth: true, anyPermission: ['courses.view', 'users.view', 'categories.view', 'instructors.view', 'packages.view', 'orders.view', 'payments.view', 'reviews.view', 'certificates.view', 'support_tickets.view'] },
        children: [
            { path: '', name: 'admin.dashboard', component: () => import('../pages/AdminDashboardPage.vue'), meta: { requiresAuth: true, anyPermission: ['courses.view', 'users.view', 'categories.view', 'instructors.view', 'packages.view'], title: 'admin.dashboard' } },
            { path: 'categories', name: 'admin.categories.index', component: () => import('../pages/AdminCategoriesPage.vue'), meta: { requiresAuth: true, permissions: ['categories.view'], title: 'admin.categories' } },
            { path: 'categories/new', name: 'admin.categories.create', component: () => import('../pages/AdminCategoryFormPage.vue'), meta: { requiresAuth: true, permissions: ['categories.create'], title: 'admin.addCategory' } },
            { path: 'categories/:id/edit', name: 'admin.categories.edit', component: () => import('../pages/AdminCategoryFormPage.vue'), meta: { requiresAuth: true, permissions: ['categories.update'], title: 'admin.editCategory' } },
            { path: 'courses', name: 'admin.courses.index', component: () => import('../pages/AdminCoursesPage.vue'), meta: { requiresAuth: true, permissions: ['courses.view'], title: 'admin.courses' } },
            { path: 'courses/new', name: 'admin.courses.create', component: () => import('../pages/AdminCourseFormPage.vue'), meta: { requiresAuth: true, permissions: ['courses.create'], title: 'admin.addCourse' } },
            { path: 'courses/:id/edit', name: 'admin.courses.edit', component: () => import('../pages/AdminCourseFormPage.vue'), meta: { requiresAuth: true, permissions: ['courses.update'], title: 'admin.editCourse' } },
            { path: 'courses/:id/curriculum', name: 'admin.courses.curriculum', component: () => import('../pages/AdminCurriculumPage.vue'), meta: { requiresAuth: true, permissions: ['courses.view', 'curriculum.view'], title: 'curriculum.heading' } },
            { path: 'packages', name: 'admin.packages.index', component: () => import('../pages/AdminPackagesPage.vue'), meta: { requiresAuth: true, permissions: ['packages.view'], title: 'packages.adminHeading' } },
            { path: 'packages/new', name: 'admin.packages.create', component: () => import('../pages/AdminPackageFormPage.vue'), meta: { requiresAuth: true, permissions: ['packages.create'], title: 'packages.add' } },
            { path: 'packages/:id/edit', name: 'admin.packages.edit', component: () => import('../pages/AdminPackageFormPage.vue'), meta: { requiresAuth: true, permissions: ['packages.update'], title: 'packages.edit' } },
            { path: 'packages/:id/courses', name: 'admin.packages.courses', component: () => import('../pages/AdminPackageCoursesPage.vue'), meta: { requiresAuth: true, permissions: ['packages.view'], title: 'packages.manageCourses' } },
            { path: 'users', name: 'admin.users.index', component: () => import('../pages/AdminUsersPage.vue'), meta: { requiresAuth: true, permissions: ['users.view'], title: 'admin.users' } },
            { path: 'users/new', name: 'admin.users.create', component: () => import('../pages/AdminUserFormPage.vue'), meta: { requiresAuth: true, permissions: ['users.manage'], title: 'admin.addUser' } },
            { path: 'users/:id/edit', name: 'admin.users.edit', component: () => import('../pages/AdminUserFormPage.vue'), meta: { requiresAuth: true, permissions: ['users.update'], title: 'admin.editUser' } },
            { path: 'instructors', name: 'admin.instructors.index', component: () => import('../pages/AdminInstructorsPage.vue'), meta: { requiresAuth: true, permissions: ['instructors.view'], title: 'admin.instructors' } },
            { path: 'instructors/new', name: 'admin.instructors.create', component: () => import('../pages/AdminInstructorFormPage.vue'), meta: { requiresAuth: true, permissions: ['instructors.manage'], title: 'admin.addInstructor' } },
            { path: 'instructors/:id/edit', name: 'admin.instructors.edit', component: () => import('../pages/AdminInstructorFormPage.vue'), meta: { requiresAuth: true, permissions: ['instructors.update'], title: 'admin.editInstructor' } },
            { path: 'orders', name: 'admin.orders.index', component: () => import('../pages/OperationalOrdersPage.vue'), meta: { requiresAuth: true, permissions: ['orders.view'], title: 'operations.orders' } },
            { path: 'orders/:id', name: 'admin.orders.show', component: () => import('../pages/OperationalOrderDetailPage.vue'), meta: { requiresAuth: true, permissions: ['orders.view'], title: 'operations.orders' } },
            { path: 'payments', name: 'admin.payments.index', component: () => import('../pages/OperationalPaymentsPage.vue'), meta: { requiresAuth: true, permissions: ['payments.view'], title: 'operations.payments' } },
            { path: 'payments/:id', name: 'admin.payments.show', component: () => import('../pages/OperationalPaymentDetailPage.vue'), meta: { requiresAuth: true, permissions: ['payments.view'], title: 'operations.payments' } },
            { path: 'reviews', name: 'admin.reviews.index', component: () => import('../pages/AdminReviewsPage.vue'), meta: { requiresAuth: true, permissions: ['reviews.view'], title: 'operations.reviews' } },
            { path: 'reviews/:id', name: 'admin.reviews.show', component: () => import('../pages/AdminReviewDetailPage.vue'), meta: { requiresAuth: true, permissions: ['reviews.view'], title: 'operations.reviews' } },
            { path: 'certificates', name: 'admin.certificates.index', component: () => import('../pages/AdminCertificatesPage.vue'), meta: { requiresAuth: true, permissions: ['certificates.view'], title: 'operations.certificates' } },
            { path: 'certificates/:id', name: 'admin.certificates.show', component: () => import('../pages/AdminCertificateDetailPage.vue'), meta: { requiresAuth: true, permissions: ['certificates.view'], title: 'operations.certificates' } },
            { path: 'support', name: 'admin.tickets.index', component: () => import('../pages/OperationalTicketsPage.vue'), meta: { requiresAuth: true, permissions: ['support_tickets.view'], title: 'operations.support' } },
            { path: 'support/:id', name: 'admin.tickets.show', component: () => import('../pages/OperationalTicketDetailPage.vue'), meta: { requiresAuth: true, permissions: ['support_tickets.view'], title: 'operations.support' } },
            { path: 'content', name: 'admin.content', redirect: { name: 'admin.courses.index' }, meta: { requiresAuth: true, permissions: ['courses.view'] } },
        ],
    },
    {
        path: '/instructor',
        component: InstructorLayout,
        meta: { requiresAuth: true, roles: ['instructor'] },
        children: [
            { path: '', name: 'instructor.dashboard', component: () => import('../pages/InstructorDashboardPage.vue'), meta: { requiresAuth: true, roles: ['instructor'], permissions: ['courses.view'], title: 'instructor.dashboard' } },
            { path: 'courses', name: 'instructor.courses.index', component: () => import('../pages/InstructorCoursesPage.vue'), meta: { requiresAuth: true, roles: ['instructor'], permissions: ['courses.view'], title: 'instructor.myCourses' } },
            { path: 'courses/:courseId', name: 'instructor.courses.show', component: () => import('../pages/InstructorCoursePage.vue'), meta: { requiresAuth: true, roles: ['instructor'], permissions: ['courses.view'], title: 'instructor.courseWorkspace' } },
            { path: 'courses/:courseId/quizzes/:quizId', name: 'instructor.quizzes.show', component: () => import('../pages/InstructorQuizPage.vue'), meta: { requiresAuth: true, roles: ['instructor'], permissions: ['courses.view', 'assignment_submissions.view'], title: 'instructor.results' } },
            { path: 'courses/:courseId/quizzes/:quizId/attempts/:attemptId', name: 'instructor.quizzes.attempts.show', component: () => import('../pages/InstructorQuizPage.vue'), meta: { requiresAuth: true, roles: ['instructor'], permissions: ['courses.view', 'assignment_submissions.view'], title: 'instructor.attempt' } },
            { path: 'courses/:courseId/assignments/:assignmentId', name: 'instructor.assignments.show', component: () => import('../pages/InstructorAssignmentPage.vue'), meta: { requiresAuth: true, roles: ['instructor'], permissions: ['courses.view', 'assignment_submissions.view'], title: 'instructor.assignments' } },
            { path: 'courses/:courseId/assignments/:assignmentId/submissions/:submissionId', name: 'instructor.submissions.show', component: () => import('../pages/InstructorSubmissionPage.vue'), meta: { requiresAuth: true, roles: ['instructor'], permissions: ['courses.view', 'assignment_submissions.view'], title: 'instructor.submissions' } },
        ],
    },
    {
        path: '/sales-support',
        component: SalesSupportLayout,
        meta: { requiresAuth: true, anyPermission: ['support_tickets.view', 'orders.view', 'payments.view'] },
        children: [
            { path: '', name: 'support.dashboard', component: () => import('../pages/SalesSupportHomePage.vue'), meta: { requiresAuth: true, anyPermission: ['support_tickets.view', 'orders.view', 'payments.view'], title: 'pages.support' } },
            { path: 'tickets', name: 'support.tickets', component: () => import('../pages/OperationalTicketsPage.vue'), meta: { requiresAuth: true, permissions: ['support_tickets.view'], title: 'operations.support' } },
            { path: 'tickets/:id', name: 'support.tickets.show', component: () => import('../pages/OperationalTicketDetailPage.vue'), meta: { requiresAuth: true, permissions: ['support_tickets.view'], title: 'operations.support' } },
            { path: 'orders', name: 'support.orders', component: () => import('../pages/OperationalOrdersPage.vue'), meta: { requiresAuth: true, permissions: ['orders.view'], title: 'operations.orders' } },
            { path: 'orders/:id', name: 'support.orders.show', component: () => import('../pages/OperationalOrderDetailPage.vue'), meta: { requiresAuth: true, permissions: ['orders.view'], title: 'operations.orders' } },
            { path: 'payments', name: 'support.payments.index', component: () => import('../pages/OperationalPaymentsPage.vue'), meta: { requiresAuth: true, permissions: ['payments.view'], title: 'operations.payments' } },
            { path: 'payments/:id', name: 'support.payments.show', component: () => import('../pages/OperationalPaymentDetailPage.vue'), meta: { requiresAuth: true, permissions: ['payments.view'], title: 'operations.payments' } },
        ],
    },
    { path: '/dashboard', name: 'dashboard', component: () => import('../pages/DashboardRedirectPage.vue'), meta: { requiresAuth: true } },
    { path: '/403', name: 'forbidden', component: () => import('../pages/UnauthorizedPage.vue'), meta: { title: 'errors.forbiddenTitle' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../pages/NotFoundPage.vue'), meta: { title: 'errors.notFoundTitle' } },
];

const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: () => ({ top: 0 }) });

router.beforeEach((to) => createAccessGuard(useAuthStore())(to));

export default router;
