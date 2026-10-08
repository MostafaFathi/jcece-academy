import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import AdminCouponsPage from '../pages/AdminCouponsPage.vue';
import AdminRefundPanel from '../components/commerce/AdminRefundPanel.vue';
import * as operations from '../api/admin-operations';
import * as admin from '../api/admin';
import * as packages from '../api/admin-packages';

const permissions = vi.hoisted(() => ({ values: [] }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ can: (permission) => permissions.values.includes(permission) }) }));
vi.mock('../api/admin-operations', () => ({ fetchCoupons: vi.fn(), createCoupon: vi.fn(), updateCoupon: vi.fn(), createOrderRefund: vi.fn(), completeOrderRefund: vi.fn(), rejectOrderRefund: vi.fn() }));
vi.mock('../api/admin', () => ({ fetchAdminCourses: vi.fn(), fetchAdminCourse: vi.fn() }));
vi.mock('../api/admin-packages', () => ({ fetchAdminPackages: vi.fn(), fetchAdminPackage: vi.fn() }));

const coupon = { id: 4, code: 'SAVE10', is_active: true, discount_type: 'fixed', discount_value: '10.00', applies_to: 'all', product_ids: [], reserved_count: 1, consumed_count: 2 };
const order = { id: 12, status: 'completed', currency: 'JOD', refund_balance: { paid: '100.00', refunded: '0.00', pending: '0.00', refundable: '100.00' }, items: [{ id: 9, title: 'Course', total: '40.00' }], refunds: [] };
function render(component, props = {}) { return mount(component, { props, global: { plugins: [i18n] } }); }

beforeEach(() => {
    vi.resetAllMocks();
    setLocale('en');
    permissions.values = ['coupons.view', 'coupons.manage', 'refunds.manage'];
    operations.fetchCoupons.mockResolvedValue({ data: [coupon], current_page: 1, last_page: 1 });
    operations.createCoupon.mockResolvedValue(coupon);
    operations.updateCoupon.mockResolvedValue(coupon);
    operations.createOrderRefund.mockResolvedValue({ id: 3 });
    admin.fetchAdminCourses.mockResolvedValue({ items: [{ id: 7, title: 'Safety course' }, { id: 8, title: 'Planning course' }], meta: { current_page: 1, last_page: 1 } });
    admin.fetchAdminCourse.mockImplementation(async (id) => ({ id, title: `Course ${id}` }));
    packages.fetchAdminPackages.mockResolvedValue({ items: [{ id: 3, title: 'Starter package' }], meta: { current_page: 1, last_page: 1 } });
    packages.fetchAdminPackage.mockImplementation(async (id) => ({ id, title: `Package ${id}` }));
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

describe('admin commerce UI', () => {
    it('filters coupons by active state and hides mutation controls without permission', async () => {
        permissions.values = ['coupons.view'];
        const wrapper = render(AdminCouponsPage);
        await flushPromises();
        expect(wrapper.text()).toContain('SAVE10');
        expect(wrapper.text()).not.toContain('Save coupon');
        await wrapper.get('select').setValue('0');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(operations.fetchCoupons).toHaveBeenCalledWith({ active: '0', page: 1 });
        wrapper.unmount();
    });

    it('creates scoped coupons and can deactivate an existing coupon', async () => {
        const wrapper = render(AdminCouponsPage);
        await flushPromises();
        const form = wrapper.findAll('form')[1];
        await form.get('input[maxlength="64"]').setValue('new20');
        await form.findAll('select')[1].setValue('course');
        await flushPromises();
        expect(form.text()).not.toContain('Eligible product IDs');
        await form.get('#coupon-products').setValue('7');
        await form.findAll('button').find((button) => button.text().includes('Apply')).trigger('click');
        await form.trigger('submit');
        await flushPromises();
        expect(operations.createCoupon).toHaveBeenCalledWith(expect.objectContaining({ code: 'new20', applies_to: 'course', product_ids: [7] }));
        await wrapper.findAll('button').find((button) => button.text().includes('SAVE10')).trigger('click');
        await wrapper.findAll('form')[1].get('input[type="checkbox"]').setValue(false);
        await wrapper.findAll('form')[1].trigger('submit');
        await flushPromises();
        expect(operations.updateCoupon).toHaveBeenCalledWith(4, expect.objectContaining({ is_active: false }));
        wrapper.unmount();
    });

    it('requires an explicit refund access effect and shows history in Arabic RTL', async () => {
        setLocale('ar');
        const wrapper = render(AdminRefundPanel, { order: { ...order, refunds: [{ id: 2, status: 'pending', amount: '10.00', currency: 'JOD', reason: 'customer_request', access_effect: 'none', internal_note: 'Private' }] } });
        await flushPromises();
        expect(wrapper.text()).toContain('Private');
        const form = wrapper.get('form');
        await form.findAll('select')[0].setValue('items');
        await form.get('input[type="checkbox"]').setValue(true);
        await form.get('input[type="number"]').setValue('40.00');
        await form.get('input[maxlength="64"]').setValue('customer_request');
        await form.trigger('submit');
        await flushPromises();
        expect(operations.createOrderRefund).toHaveBeenCalledWith(12, expect.objectContaining({ amount: '40.00', access_effect: 'items', order_item_ids: [9] }));
        expect(wrapper.emitted('refresh')).toHaveLength(1);
        wrapper.unmount();
    });
});
