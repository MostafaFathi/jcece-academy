import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import i18n, { setLocale } from '../i18n';
import FinancialDocumentsPanel from '../components/commerce/FinancialDocumentsPanel.vue';
import OrdersPage from '../pages/OrdersPage.vue';
import OperationalOrderDetailPage from '../pages/OperationalOrderDetailPage.vue';
import * as commerce from '../api/commerce';
import * as operations from '../api/admin-operations';

const permissions = vi.hoisted(() => ({ values: [] }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ can: (permission) => permissions.values.includes(permission) }) }));
vi.mock('vue-router', () => ({ useRoute: () => ({ path: '/admin/orders/12', params: { id: 12 } }) }));
vi.mock('../api/commerce', () => ({ issueOrderDocuments: vi.fn(), downloadOrderDocument: vi.fn(), fetchOrders: vi.fn() }));
vi.mock('../api/admin-operations', () => ({ issueOperationalDocuments: vi.fn(), downloadOperationalDocument: vi.fn(), fetchOperationalOrder: vi.fn(), provisionOrderAccess: vi.fn(), retryTransactionalDelivery: vi.fn() }));

const receipt = { id: 8, kind: 'purchase', document_number: 'JCEC-OR-2026-123', amount: '100.00', currency: 'JOD', issued_at: '2026-10-04T12:00:00Z' };
const refund = { id: 9, kind: 'refund', document_number: 'JCEC-RR-2026-456', amount: '25.00', currency: 'JOD', issued_at: '2026-10-04T13:00:00Z' };
const order = { id: 12, status: 'completed', paid_at: '2026-10-04T12:00:00Z', financial_documents: [receipt, refund] };
function render(props) { return mount(FinancialDocumentsPanel, { props, global: { plugins: [i18n] } }); }

beforeEach(() => { vi.resetAllMocks(); setLocale('en'); permissions.values = []; });

describe('financial document UI', () => {
    it('shows unavailable state for unpaid order and no download action', () => {
        const wrapper = render({ order: { id: 12, status: 'pending', paid_at: null, financial_documents: [] } });
        expect(wrapper.text()).toContain('available only after the order is completed');
        expect(wrapper.text()).not.toContain('Download securely');
    });

    it('shows purchase and refund receipts and downloads through protected student API', async () => {
        const wrapper = render({ order });
        expect(wrapper.text()).toContain('Order receipt');
        expect(wrapper.text()).toContain('Refund receipt');
        expect(wrapper.text()).toContain(receipt.document_number);
        await wrapper.findAll('button').find((button) => button.text() === 'Download securely').trigger('click');
        await flushPromises();
        expect(commerce.downloadOrderDocument).toHaveBeenCalledWith(receipt.id);
        setLocale('ar');
        await nextTick();
        expect(wrapper.attributes('dir')).toBe('rtl');
        expect(wrapper.text()).toContain('إيصال استرداد');
    });

    it('marks receipt availability in student order history', async () => {
        commerce.fetchOrders.mockResolvedValue({ items: [{ ...order, order_number: 'JCEC-ORDER-123', currency: 'JOD', total: '100.00', created_at: '2026-10-04T12:00:00Z' }], meta: { last_page: 1 } });
        const wrapper = mount(OrdersPage, { global: { plugins: [i18n], stubs: { RouterLink: { template: '<a><slot /></a>' } } } });
        await flushPromises();
        expect(wrapper.text()).toContain('Receipt available');
        expect(wrapper.text()).toContain('JCEC-ORDER-123');
    });

    it('prepares historical documents idempotently through admin endpoint', async () => {
        operations.issueOperationalDocuments.mockResolvedValue([]);
        const wrapper = render({ order: { ...order, financial_documents: [] }, admin: true });
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(operations.issueOperationalDocuments).toHaveBeenCalledWith(order.id);
        expect(wrapper.emitted('refresh')).toHaveLength(1);
    });

    it('shows request error and remains retryable', async () => {
        commerce.issueOrderDocuments.mockRejectedValue({ status: 500, message: 'Temporary failure' });
        const wrapper = render({ order: { ...order, financial_documents: [] } });
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('unexpected server error');
        expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
    });

    it('hides privileged document and delivery panels until the admin has explicit permissions', async () => {
        operations.fetchOperationalOrder.mockResolvedValue({ ...order, order_number: 'JCEC-ORDER-123', currency: 'JOD', total: '100.00', customer_name: 'Student', customer_email: 'student@example.test', customer_phone: '0599000000', placed_at: '2026-10-04T12:00:00Z', items: [], payments: [], refunds: [], transactional_deliveries: [{ id: 3, status: 'failed', message_type: 'purchase_completed', recipient_email: 'student@example.test', locale: 'en', attempts: 1 }] });
        const stubs = { RouterLink: { template: '<a><slot /></a>' } };
        const denied = mount(OperationalOrderDetailPage, { global: { plugins: [i18n], stubs } });
        await flushPromises();
        expect(denied.text()).not.toContain('Financial documents');
        expect(denied.text()).not.toContain('Transactional email delivery');
        denied.unmount();

        permissions.values = ['financial_documents.view', 'transactional_deliveries.view', 'transactional_deliveries.retry'];
        const allowed = mount(OperationalOrderDetailPage, { global: { plugins: [i18n], stubs } });
        await flushPromises();
        expect(allowed.text()).toContain('Financial documents');
        expect(allowed.text()).toContain('Transactional email delivery');
        expect(allowed.text()).toContain('Retry existing delivery');
    });
});
