import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import i18n, { setLocale } from '../i18n';
import { useAuthStore } from '../stores/auth';
import * as support from '../api/support';
import * as learning from '../api/learning';
import * as commerce from '../api/commerce';
import SupportTicketsPage from '../pages/SupportTicketsPage.vue';
import CreateSupportTicketPage from '../pages/CreateSupportTicketPage.vue';
import SupportTicketDetailPage from '../pages/SupportTicketDetailPage.vue';
import { routes } from '../router';
import { navigationByArea } from '../composables/navigation';

const push = vi.fn();
vi.mock('vue-router', async (importOriginal) => ({ ...(await importOriginal()), useRouter: () => ({ push }) }));
vi.mock('../api/support', () => Object.fromEntries(['fetchTickets', 'createTicket', 'fetchTicket', 'fetchTicketMessages', 'replyToTicket', 'reopenTicket', 'downloadTicketAttachment'].map((name) => [name, vi.fn()])));
vi.mock('../api/learning', () => ({ fetchMyCourses: vi.fn() }));
vi.mock('../api/commerce', () => ({ fetchOrders: vi.fn() }));
const ticket = (extra = {}) => ({ id: 7, ticket_number: 'JCEC-7', subject: 'Help needed', category: 'technical', priority: 'normal', status: 'open', last_reply_at: '2026-09-30T10:00:00Z', created_at: '2026-09-30T09:00:00Z', related_order: null, related_course: null, ...extra });
const message = (extra = {}) => ({ id: 3, author: { id: 1, name: 'Student' }, body: 'Public question', attachments: [{ id: 5, filename: 'evidence.txt' }], created_at: '2026-09-30T09:00:00Z', ...extra });
const collection = (items, current_page = 1, last_page = 1) => ({ items, meta: { current_page, last_page } });
const render = (component, props = {}) => mount(component, { props, global: { plugins: [i18n, createPinia()], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } });
const button = (wrapper, text) => wrapper.findAll('button').find((item) => item.text().includes(text));
function deferred() { let resolve; const promise = new Promise((yes) => { resolve = yes; }); return { promise, resolve }; }
beforeEach(() => {
    vi.resetAllMocks(); setLocale('en'); setActivePinia(createPinia()); useAuthStore().setUser({ id: 1, name: 'Student' });
    support.fetchTickets.mockResolvedValue(collection([ticket()])); support.fetchTicket.mockResolvedValue(ticket()); support.fetchTicketMessages.mockResolvedValue(collection([message()])); support.createTicket.mockResolvedValue(ticket()); support.replyToTicket.mockResolvedValue(message({ id: 4, body: 'New reply' })); support.reopenTicket.mockResolvedValue(ticket({ status: 'open' }));
    learning.fetchMyCourses.mockResolvedValue(collection([{ id: 2, has_access: true, course: { id: 9, title: 'My course' } }, { id: 3, has_access: false, course: { id: 10, title: 'Expired course' } }]));
    commerce.fetchOrders.mockResolvedValue(collection([{ id: 8, order_number: 'ORDER-8' }]));
});

describe('student support list and creation', () => {
    it('keeps support routes student-scoped and staff navigation separate', () => {
        const studentRoutes = routes.find((route) => route.path === '/student').children.filter((route) => route.path.startsWith('support'));
        expect(studentRoutes.map((route) => route.name)).toEqual(['student.support.index', 'student.support.create', 'student.support.show']);
        expect(studentRoutes.every((route) => route.meta.requiresAuth)).toBe(true);
        expect(navigationByArea.student.map((item) => item.route)).toContain('student.support.index');
        expect(navigationByArea.student.map((item) => item.route)).not.toContain('support.tickets');
    });
    it('shows loading, empty, pagination and localized workflow status', async () => {
        const pending = deferred(); support.fetchTickets.mockReturnValueOnce(pending.promise);
        const wrapper = render(SupportTicketsPage); expect(wrapper.text()).toContain('Loading');
        pending.resolve(collection([ticket({ status: 'waiting_for_student' })], 1, 2)); await flushPromises();
        expect(wrapper.text()).toContain('Waiting for you'); await button(wrapper, 'Next').trigger('click'); await flushPromises(); expect(support.fetchTickets).toHaveBeenCalledWith(2); wrapper.unmount();
        support.fetchTickets.mockResolvedValue(collection([])); const empty = render(SupportTicketsPage); await flushPromises(); expect(empty.text()).toContain('No support tickets'); empty.unmount();
    });
    it('shows retry after list failure', async () => {
        support.fetchTickets.mockRejectedValueOnce({ status: 500 }); const wrapper = render(SupportTicketsPage); await flushPromises(); expect(wrapper.text()).toContain('Unable to load');
        await button(wrapper, 'Try again').trigger('click'); await flushPromises(); expect(wrapper.text()).toContain('JCEC-7'); wrapper.unmount();
    });
    it('offers actual categories and only owned orders and active courses', async () => {
        const wrapper = render(CreateSupportTicketPage); await flushPromises();
        expect(wrapper.get('#ticket-category').findAll('option')).toHaveLength(8);
        expect(wrapper.get('#related-course').text()).toContain('My course'); expect(wrapper.get('#related-course').text()).not.toContain('Expired course');
        expect(wrapper.get('#related-order').text()).toContain('ORDER-8'); wrapper.unmount();
    });
    it('validates, sends safe multipart fields and prevents duplicate creation', async () => {
        const pending = deferred(); support.createTicket.mockReturnValue(pending.promise);
        const wrapper = render(CreateSupportTicketPage); await flushPromises();
        await wrapper.get('#ticket-subject').setValue('Question'); await wrapper.get('#ticket-category').setValue('general'); await wrapper.get('#ticket-body').setValue('Need help');
        await wrapper.get('#related-course').setValue('9'); await wrapper.get('#related-order').setValue('8');
        await wrapper.get('form').trigger('submit'); await wrapper.get('form').trigger('submit');
        expect(support.createTicket).toHaveBeenCalledTimes(1);
        const sent = support.createTicket.mock.calls[0][0]; expect(Array.from(sent.keys())).toEqual(['subject', 'category', 'body', 'related_course_id', 'related_order_id']);
        expect(sent.get('related_course_id')).toBe('9'); expect(sent.get('related_order_id')).toBe('8');
        pending.resolve(ticket()); await flushPromises(); expect(push).toHaveBeenCalledWith({ name: 'student.support.show', params: { id: 7 } }); wrapper.unmount();
    });
    it('rejects invalid file selection before upload', async () => {
        const wrapper = render(CreateSupportTicketPage); await flushPromises();
        await wrapper.get('#ticket-subject').setValue('Question'); await wrapper.get('#ticket-category').setValue('general'); await wrapper.get('#ticket-body').setValue('Need help');
        const input = wrapper.get('#ticket-files'); Object.defineProperty(input.element, 'files', { configurable: true, value: [new File(['x'], 'bad.exe')] }); await input.trigger('change');
        await wrapper.get('form').trigger('submit'); expect(wrapper.text()).toContain('not allowed'); expect(support.createTicket).not.toHaveBeenCalled(); wrapper.unmount();
    });
});

describe('student-visible ticket conversation', () => {
    it('renders chronological public conversation and never renders internal metadata', async () => {
        support.fetchTicket.mockResolvedValue(ticket({ assigned_to: 88, activities: [{ body: 'Secret activity' }] }));
        support.fetchTicketMessages.mockResolvedValue(collection([message({ storage_path: 'secret/path', is_internal: false }), message({ id: 4, author: { id: 9, name: 'Agent' }, body: 'Public staff reply', attachments: [], created_at: '2026-09-30T10:00:00Z' })]));
        const wrapper = render(SupportTicketDetailPage, { id: '7' }); await flushPromises();
        expect(wrapper.text()).toContain('Public question'); expect(wrapper.text()).toContain('Public staff reply'); expect(wrapper.text().indexOf('Public question')).toBeLessThan(wrapper.text().indexOf('Public staff reply'));
        expect(wrapper.text()).toContain('Academy team'); expect(wrapper.text()).not.toContain('Secret activity'); expect(wrapper.text()).not.toContain('secret/path'); expect(wrapper.text()).not.toContain('assigned_to'); wrapper.unmount();
    });
    it.each([['waiting_for_student', 'in_progress'], ['resolved', 'open']])('refetches the server transition after a %s reply', async (before, after) => {
        support.fetchTicket.mockResolvedValueOnce(ticket({ status: before })).mockResolvedValueOnce(ticket({ status: after }));
        const wrapper = render(SupportTicketDetailPage, { id: '7' }); await flushPromises(); await wrapper.get('#ticket-reply').setValue('Thanks');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(support.replyToTicket).toHaveBeenCalledTimes(1); expect(wrapper.text()).toContain(after === 'open' ? 'Open' : 'In progress');
        expect(support.fetchTicket).toHaveBeenCalledTimes(2); wrapper.unmount();
    });
    it('blocks ordinary replies to closed tickets and explicitly reopens from server', async () => {
        support.fetchTicket.mockResolvedValueOnce(ticket({ status: 'closed' })).mockResolvedValueOnce(ticket({ status: 'open' }));
        const wrapper = render(SupportTicketDetailPage, { id: '7' }); await flushPromises();
        expect(wrapper.find('#ticket-reply').exists()).toBe(false); expect(wrapper.text()).toContain('closed');
        await button(wrapper, 'Reopen ticket').trigger('click'); await flushPromises(); expect(support.reopenTicket).toHaveBeenCalledWith('7'); expect(wrapper.find('#ticket-reply').exists()).toBe(true); wrapper.unmount();
    });
    it('guards duplicate replies, validates files and downloads through protected API', async () => {
        const pending = deferred(); support.replyToTicket.mockReturnValue(pending.promise);
        const wrapper = render(SupportTicketDetailPage, { id: '7' }); await flushPromises();
        await button(wrapper, 'Download').trigger('click'); await flushPromises(); expect(support.downloadTicketAttachment).toHaveBeenCalledWith(5);
        await wrapper.get('#ticket-reply').setValue('Thanks'); await wrapper.get('form').trigger('submit'); await wrapper.get('form').trigger('submit');
        expect(support.replyToTicket).toHaveBeenCalledTimes(1); expect(button(wrapper, 'Send reply').attributes('disabled')).toBeDefined(); pending.resolve(message()); await flushPromises(); wrapper.unmount();
    });
    it('shows failed download and Arabic RTL / English LTR labels', async () => {
        support.downloadTicketAttachment.mockRejectedValue({ status: 403 });
        const wrapper = render(SupportTicketDetailPage, { id: '7' }); await flushPromises(); await button(wrapper, 'Download').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Unable to download'); expect(wrapper.attributes('dir')).toBe('ltr');
        setLocale('ar'); await flushPromises(); expect(wrapper.attributes('dir')).toBe('rtl'); expect(wrapper.text()).toContain('المحادثة'); wrapper.unmount();
    });
});
