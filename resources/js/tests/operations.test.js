import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import { canAccessRoute } from '../router/access';
import { routes } from '../router';
import { navigationByArea, visibleNavigation } from '../composables/navigation';
import * as operations from '../api/admin-operations';
import OperationalOrdersPage from '../pages/OperationalOrdersPage.vue';
import OperationalOrderDetailPage from '../pages/OperationalOrderDetailPage.vue';
import OperationalPaymentDetailPage from '../pages/OperationalPaymentDetailPage.vue';
import OperationalPaymentsPage from '../pages/OperationalPaymentsPage.vue';
import AdminReviewDetailPage from '../pages/AdminReviewDetailPage.vue';
import AdminReviewsPage from '../pages/AdminReviewsPage.vue';
import AdminCertificateDetailPage from '../pages/AdminCertificateDetailPage.vue';
import AdminCertificatesPage from '../pages/AdminCertificatesPage.vue';
import OperationalTicketDetailPage from '../pages/OperationalTicketDetailPage.vue';
import OperationalTicketsPage from '../pages/OperationalTicketsPage.vue';
import SalesSupportHomePage from '../pages/SalesSupportHomePage.vue';

const state = vi.hoisted(() => ({ route: { path: '/admin/orders', params: { id: '4' }, query: {} }, push: vi.fn(), permissions: [], user: { id: 8 } }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route, useRouter: () => ({ push: state.push }) }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ user: state.user, can: (permission) => state.permissions.includes(permission), canAny: (permissions) => permissions.some((permission) => state.permissions.includes(permission)), hasRole: () => false, hasAnyRole: () => false }) }));
vi.mock('../api/admin-operations', () => Object.fromEntries([
    'fetchOperationalOrders', 'fetchOperationalOrder', 'provisionOrderAccess', 'fetchOperationalPayments', 'fetchOperationalPayment', 'downloadOperationalProof', 'approveOperationalPayment', 'rejectOperationalPayment',
    'fetchModerationReviews', 'fetchModerationReview', 'publishModerationReview', 'rejectModerationReview', 'hideModerationReview',
    'fetchOperationalCertificates', 'fetchOperationalCertificate', 'downloadOperationalCertificate', 'revokeOperationalCertificate', 'reissueOperationalCertificate',
    'fetchOperationalTickets', 'fetchOperationalTicket', 'fetchOperationalTicketMessages', 'updateOperationalTicket', 'assignOperationalTicket', 'postOperationalTicketMessage', 'downloadOperationalAttachment',
].map((name) => [name, vi.fn()])));

const collection = (items) => ({ items, meta: { current_page: 1, last_page: 2, total: 27 } });
const order = { id: 4, order_number: 'JCEC-001', status: 'paid', customer_name: 'Student', customer_email: 'student@example.test', total: '130.00', currency: 'ILS', placed_at: '2026-09-01T10:00:00Z', items: [{ id: 1, title: 'Historical package', quantity: 1, total: '130.00', access_duration_days: null, package_courses: [{ id: 2, course_title: 'Historical course' }] }], payments: [{ id: 9, status: 'pending_review', amount: '130.00', currency: 'ILS' }] };
const payment = { id: 9, order_id: 4, order_number: 'JCEC-001', status: 'pending_review', amount: '130.00', currency: 'ILS', method: 'bank_transfer', proof_available: true, created_at: '2026-09-01T10:00:00Z' };
const review = { id: 3, course: { title: 'Course' }, reviewer: { id: 5, name: 'Student' }, rating: 4, title: 'Title', body: 'Original text', status: 'pending', submitted_at: '2026-09-01T10:00:00Z', history: [] };
const certificate = { id: 6, certificate_number: 'CERT-006', student_name: 'Snapshot student', course_title: 'Snapshot course', status: 'issued', issued_at: '2026-09-01T10:00:00Z' };
const ticket = { id: 7, ticket_number: 'SUP-007', subject: 'Help', student: { id: 5, name: 'Student' }, category: 'technical', priority: 'normal', status: 'open', updated_at: '2026-09-01T10:00:00Z', activities: [] };
const message = { id: 11, is_internal: true, author: { id: 8, name: 'Staff' }, body: 'Private note', attachments: [{ id: 12, filename: 'note.pdf' }], created_at: '2026-09-01T10:00:00Z' };
const auth = (permissions) => ({ can: (permission) => permissions.includes(permission), canAny: (required) => required.some((permission) => permissions.includes(permission)), hasAnyRole: () => false });
const render = (component) => mount(component, { global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } });
const button = (wrapper, label) => wrapper.findAll('button').find((candidate) => candidate.text().includes(label));

beforeEach(() => {
    vi.resetAllMocks();
    setLocale('en');
    state.route = { path: '/admin/orders', params: { id: '4' }, query: {} };
    state.permissions = ['orders.view', 'orders.manage', 'payments.view', 'payments.manage', 'reviews.view', 'reviews.moderate', 'certificates.view', 'certificates.revoke', 'certificates.issue', 'support_tickets.view', 'support_tickets.manage', 'support_tickets.reply'];
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    operations.fetchOperationalOrders.mockResolvedValue(collection([order]));
    operations.fetchOperationalOrder.mockResolvedValue(order);
    operations.fetchOperationalPayment.mockResolvedValue(payment);
    operations.fetchOperationalPayments.mockResolvedValue(collection([payment]));
    operations.fetchModerationReview.mockResolvedValue(review);
    operations.fetchModerationReviews.mockResolvedValue(collection([review]));
    operations.fetchOperationalCertificate.mockResolvedValue(certificate);
    operations.fetchOperationalCertificates.mockResolvedValue(collection([certificate]));
    operations.fetchOperationalTicket.mockResolvedValue(ticket);
    operations.fetchOperationalTickets.mockResolvedValue(collection([ticket]));
    operations.fetchOperationalTicketMessages.mockResolvedValue(collection([message]));
    operations.postOperationalTicketMessage.mockResolvedValue(message);
    operations.reissueOperationalCertificate.mockResolvedValue({ id: 10 });
});

describe('Phase 13C operations', () => {
    it('restricts operations routes and navigation by effective permission, including Sales Support', () => {
        const admin = routes.find((route) => route.path === '/admin');
        const support = routes.find((route) => route.path === '/sales-support');
        expect(admin.children.find((route) => route.name === 'admin.reviews.index').meta.permissions).toEqual(['reviews.view']);
        expect(support.children.find((route) => route.name === 'support.payments.index').meta.permissions).toEqual(['payments.view']);
        expect(support.children.some((route) => route.name?.includes('certificates'))).toBe(false);
        expect(canAccessRoute(auth(['orders.view']), admin.children.find((route) => route.name === 'admin.orders.index').meta)).toBe(true);
        expect(canAccessRoute(auth(['orders.view']), admin.children.find((route) => route.name === 'admin.reviews.index').meta)).toBe(false);
        expect(visibleNavigation(navigationByArea.support, auth(['support_tickets.view'])).map((item) => item.route)).toEqual(['support.dashboard', 'support.tickets']);
    });

    it('shows only authorized Sales Support queues', () => {
        state.route.path = '/sales-support'; state.permissions = ['support_tickets.view'];
        const wrapper = render(SalesSupportHomePage);
        expect(wrapper.text()).toContain('Support tickets');
        expect(wrapper.text()).not.toContain('Payment review');
        expect(wrapper.text()).not.toContain('Review moderation');
        wrapper.unmount();
    });

    it('uses server-side order search and shows historical totals and status', async () => {
        const wrapper = render(OperationalOrdersPage); await flushPromises();
        expect(wrapper.text()).toContain('JCEC-001'); expect(wrapper.text()).toContain('ILS'); expect(wrapper.text()).toContain('Paid — access incomplete');
        await wrapper.get('#order-search').setValue('student@example.test'); await wrapper.get('#order-status').setValue('paid'); await wrapper.get('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.orders.index', query: { search: 'student@example.test', status: 'paid' } });
        wrapper.unmount();
    });

    it('keeps historical package snapshots and reconciles only a paid order after confirmation', async () => {
        const wrapper = render(OperationalOrderDetailPage); await flushPromises();
        expect(wrapper.text()).toContain('Historical package'); expect(wrapper.text()).toContain('Historical course'); expect(wrapper.text()).toContain('130.00 ILS');
        await button(wrapper, 'Reconcile access').trigger('click'); await flushPromises();
        expect(operations.provisionOrderAccess).toHaveBeenCalledTimes(1);
        expect(operations.fetchOperationalOrder).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it('does not claim reconciliation succeeded after a server failure', async () => {
        operations.provisionOrderAccess.mockRejectedValue({ status: 422 });
        const wrapper = render(OperationalOrderDetailPage); await flushPromises();
        await button(wrapper, 'Reconcile access').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Paid — access incomplete'); expect(wrapper.text()).toContain('could not be completed');
        wrapper.unmount();
    });

    it('loads payment, review, certificate and support queues with supported server filters', async () => {
        const payments = render(OperationalPaymentsPage); await flushPromises();
        expect(operations.fetchOperationalPayments).toHaveBeenCalledWith({ page: 1, status: 'pending_review' });
        await payments.get('#payment-order').setValue('4'); await payments.get('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.payments.index', query: { status: 'pending_review', order_id: 4 } }); payments.unmount();

        const reviews = render(AdminReviewsPage); await flushPromises();
        expect(reviews.text()).toContain('Course');
        await reviews.get('#review-status').setValue('hidden'); await reviews.get('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.reviews.index', query: { status: 'hidden' } }); reviews.unmount();

        const certificates = render(AdminCertificatesPage); await flushPromises();
        expect(certificates.text()).toContain('Snapshot student');
        await certificates.get('#certificate-status').setValue('revoked'); await certificates.get('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.certificates.index', query: { status: 'revoked' } }); certificates.unmount();

        const tickets = render(OperationalTicketsPage); await flushPromises();
        expect(tickets.text()).toContain('SUP-007');
        await tickets.get('#ticket-status-filter').setValue('open'); await tickets.get('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.tickets.index', query: { status: 'open' } }); tickets.unmount();
    });

    it('downloads payment proof through the protected adapter and confirms approval once', async () => {
        const wrapper = render(OperationalPaymentDetailPage); await flushPromises();
        await button(wrapper, 'Download proof').trigger('click'); await flushPromises();
        expect(operations.downloadOperationalProof).toHaveBeenCalledWith(9);
        window.confirm.mockReturnValueOnce(false);
        await button(wrapper, 'Approve payment').trigger('click');
        expect(operations.approveOperationalPayment).not.toHaveBeenCalled();
        await button(wrapper, 'Approve payment').trigger('click'); await flushPromises();
        expect(operations.approveOperationalPayment).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });

    it('requires payment rejection reason and hides actions without manage permission', async () => {
        const wrapper = render(OperationalPaymentDetailPage); await flushPromises();
        await button(wrapper, 'Reject payment').trigger('click'); await wrapper.get('#operation-reason').setValue('Invalid proof');
        await button(wrapper, 'Confirm').trigger('click'); await flushPromises();
        expect(operations.rejectOperationalPayment).toHaveBeenCalledWith(9, 'Invalid proof'); wrapper.unmount();
        state.permissions = ['payments.view'];
        const readOnly = render(OperationalPaymentDetailPage); await flushPromises();
        expect(button(readOnly, 'Approve payment')).toBeUndefined(); readOnly.unmount();
    });

    it('blocks a duplicate pending approval request', async () => {
        let complete;
        operations.approveOperationalPayment.mockReturnValue(new Promise((resolve) => { complete = resolve; }));
        const wrapper = render(OperationalPaymentDetailPage); await flushPromises();
        await button(wrapper, 'Approve payment').trigger('click');
        await button(wrapper, 'Approve payment').trigger('click');
        expect(operations.approveOperationalPayment).toHaveBeenCalledTimes(1);
        complete({ ...payment, status: 'paid' }); await flushPromises(); wrapper.unmount();
    });

    it('applies exact review transitions and refetches the server record', async () => {
        operations.fetchModerationReview.mockResolvedValueOnce(review).mockResolvedValueOnce({ ...review, status: 'published' });
        const wrapper = render(AdminReviewDetailPage); await flushPromises();
        await button(wrapper, 'Publish').trigger('click'); await flushPromises();
        expect(operations.publishModerationReview).toHaveBeenCalledWith(3);
        expect(button(wrapper, 'Hide')).toBeDefined(); expect(button(wrapper, 'Reject')).toBeUndefined();
        await button(wrapper, 'Hide').trigger('click'); await wrapper.get('#operation-reason').setValue('Needs moderation'); await button(wrapper, 'Confirm').trigger('click'); await flushPromises();
        expect(operations.hideModerationReview).toHaveBeenCalledWith(3, 'Needs moderation'); wrapper.unmount();
    });

    it('revokes with a reason and reissues a distinct certificate record', async () => {
        operations.fetchOperationalCertificate.mockResolvedValueOnce(certificate).mockResolvedValueOnce({ ...certificate, status: 'revoked' });
        const wrapper = render(AdminCertificateDetailPage); await flushPromises();
        expect(wrapper.text()).toContain('Snapshot student');
        await button(wrapper, 'Revoke certificate').trigger('click'); await wrapper.get('#operation-reason').setValue('Incorrect name'); await button(wrapper, 'Confirm').trigger('click'); await flushPromises();
        expect(operations.revokeOperationalCertificate).toHaveBeenCalledWith(6, 'Incorrect name');
        await button(wrapper, 'Issue a new certificate').trigger('click'); await flushPromises();
        expect(operations.reissueOperationalCertificate).toHaveBeenCalledWith(6);
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.certificates.show', params: { id: 10 }, query: { reissued: 1 } });
        wrapper.unmount();
    });

    it('keeps internal notes distinct, sends multipart and reloads after a staff reply', async () => {
        state.route = { path: '/sales-support/tickets/7', params: { id: '7' }, query: {} };
        const wrapper = render(OperationalTicketDetailPage); await flushPromises();
        expect(wrapper.text()).toContain('Internal note — not visible to student');
        await wrapper.get('#staff-message').setValue('We are checking');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        const form = operations.postOperationalTicketMessage.mock.calls[0][1];
        expect(form).toBeInstanceOf(FormData); expect(form.get('is_internal')).toBe('0');
        expect(operations.fetchOperationalTicket).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it('sends an internal note and attachment as multipart without exposing a storage path', async () => {
        state.route = { path: '/sales-support/tickets/7', params: { id: '7' }, query: {} };
        const wrapper = render(OperationalTicketDetailPage); await flushPromises();
        await button(wrapper, 'Internal note — not visible to student').trigger('click');
        await wrapper.get('#staff-message').setValue('Private update');
        const input = wrapper.get('#staff-files');
        Object.defineProperty(input.element, 'files', { value: [new File(['proof'], 'note.pdf', { type: 'application/pdf' })], configurable: true });
        await input.trigger('change'); await wrapper.get('form').trigger('submit'); await flushPromises();
        const form = operations.postOperationalTicketMessage.mock.calls[0][1];
        expect(form.get('is_internal')).toBe('1'); expect(form.getAll('attachments[]')).toHaveLength(1);
        expect(wrapper.html()).not.toContain('storage_path');
        wrapper.unmount();
    });

    it('uses server timestamp and warns on stale management updates without auto-retry', async () => {
        state.route = { path: '/sales-support/tickets/7', params: { id: '7' }, query: {} };
        operations.updateOperationalTicket.mockRejectedValue({ status: 422, errors: { expected_updated_at: ['Stale'] } });
        const wrapper = render(OperationalTicketDetailPage); await flushPromises();
        await button(wrapper, 'Start progress').trigger('click'); await flushPromises();
        expect(operations.updateOperationalTicket).toHaveBeenCalledWith(7, { status: 'in_progress', expected_updated_at: ticket.updated_at });
        expect(operations.updateOperationalTicket).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain('changed elsewhere');
        expect(operations.fetchOperationalTicket).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it('renders RTL and translated operational status labels', async () => {
        setLocale('ar');
        const wrapper = render(OperationalOrdersPage); await flushPromises();
        expect(wrapper.attributes('dir')).toBe('rtl'); expect(wrapper.text()).toContain('مدفوع — الوصول غير مكتمل');
        wrapper.unmount();
    });
});
