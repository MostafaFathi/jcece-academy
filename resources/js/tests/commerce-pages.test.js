import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import i18n, { setLocale } from '../i18n';
import { useAuthStore } from '../stores/auth';
import { useCartStore } from '../stores/cart';
import CartPage from '../pages/CartPage.vue';
import CheckoutPage from '../pages/CheckoutPage.vue';
import OrdersPage from '../pages/OrdersPage.vue';
import OrderDetailPage from '../pages/OrderDetailPage.vue';
import AddToCartButton from '../components/commerce/AddToCartButton.vue';
import ManualPaymentForm from '../components/commerce/ManualPaymentForm.vue';
import CommerceStatus from '../components/commerce/CommerceStatus.vue';
import MoneyAmount from '../components/commerce/MoneyAmount.vue';
import * as commerce from '../api/commerce';
import { accessState, canSubmitPayment, moneyText, orderStatuses, paymentStatuses } from '../utils/commerce';

const router = vi.hoisted(() => ({ push: vi.fn(), resolve: vi.fn(() => ({ fullPath: '/courses/bim' })), route: { query: {} } }));
vi.mock('vue-router', () => ({ useRouter: () => router, useRoute: () => router.route }));
vi.mock('../api/commerce', () => ({ fetchCart: vi.fn(), addCartItem: vi.fn(), removeCartItem: vi.fn(), clearCart: vi.fn(), applyCartCoupon: vi.fn(), removeCartCoupon: vi.fn(), checkout: vi.fn(), fetchOrders: vi.fn(), fetchOrder: vi.fn(), submitPayment: vi.fn(), downloadPaymentProof: vi.fn() }));
const item = { id: 7, purchasable_type: 'course', purchasable_id: 2, available: true, product: { id: 2, slug: 'bim', title: 'BIM Essentials', price: '10.25', access_duration_days: 90 } };
const cartData = (items = [item]) => ({ id: 1, items, item_count: items.length, estimated_total: items.length ? '10.25' : '0.00', currency: 'JOD' });
const order = (status = 'pending', payments = []) => ({ id: 12, order_number: 'JCEC-12', status, currency: 'JOD', subtotal: '10.25', discount_total: '0.00', tax_total: '0.00', total: '10.25', customer_name: 'Student', customer_email: 'student@example.test', customer_phone: '0599000000', payment_proof_max_kilobytes: 1024, items: [{ id: 3, title: 'Historical Package', purchasable_type: 'package', quantity: 1, unit_price: '10.25', total: '10.25', discount_amount: '0.00', access_duration_days: 180, package_courses: [{ id: 9, course_title: 'Historical Course' }] }], payments, created_at: '2026-09-28T00:00:00Z' });
let pinia;
function render(component, props = {}) { return mount(component, { props, global: { plugins: [pinia, i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } }); }
function selectFile(wrapper, file) {
    const input = wrapper.get('input[type="file"]');
    Object.defineProperty(input.element, 'files', { value: file ? [file] : [], configurable: true });
    return input.trigger('change');
}
function deferred() { let resolve; const promise = new Promise((done) => { resolve = done; }); return { promise, resolve }; }

describe('student commerce UI', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        sessionStorage.clear();
        setLocale('en');
        pinia = createPinia();
        setActivePinia(pinia);
        useAuthStore().setUser({ id: 1, name: 'Student', email: 'student@example.test', phone: '0599000000', roles: ['student'], permissions: [] });
        router.resolve.mockReturnValue({ fullPath: '/courses/bim' });
        router.route.query = {};
        commerce.fetchCart.mockResolvedValue(cartData());
        commerce.fetchOrder.mockResolvedValue(order());
    });

    it('renders cart loading, empty state and server amount/currency', async () => {
        const pending = deferred();
        commerce.fetchCart.mockReturnValueOnce(pending.promise);
        const wrapper = render(CartPage);
        await nextTick();
        expect(wrapper.text()).toContain('Loading');
        pending.resolve(cartData([]));
        await flushPromises();
        expect(wrapper.text()).toContain('Your cart is empty');
        commerce.fetchCart.mockResolvedValue(cartData());
        await useCartStore().load();
        await nextTick();
        expect(wrapper.text()).toContain('BIM Essentials');
        expect(wrapper.text()).toContain('JOD');
        expect(wrapper.text()).toContain('10.25');
        expect(wrapper.text()).toContain('90 days');
    });

    it('removes a cart item with its actual ID and renders the cleared resource', async () => {
        commerce.removeCartItem.mockResolvedValue(cartData([]));
        const wrapper = render(CartPage);
        await flushPromises();
        await wrapper.get('button[aria-label="Remove BIM Essentials"]').trigger('click');
        await flushPromises();
        expect(commerce.removeCartItem).toHaveBeenCalledWith(7);
        expect(wrapper.text()).toContain('Your cart is empty');
    });

    it('applies and removes a coupon with authoritative totals in both locales', async () => {
        commerce.applyCartCoupon.mockResolvedValue({ ...cartData(), coupon_code: 'SAVE10', subtotal: '10.25', coupon_discount: '1.00', promotional_savings: '0.00', estimated_total: '9.25' });
        commerce.removeCartCoupon.mockResolvedValue({ ...cartData(), coupon_code: null, subtotal: '10.25', coupon_discount: '0.00', promotional_savings: '0.00' });
        const wrapper = render(CartPage);
        await flushPromises();
        await wrapper.get('#cart-coupon').setValue('save10');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(commerce.applyCartCoupon).toHaveBeenCalledWith('save10');
        expect(wrapper.text()).toContain('SAVE10');
        expect(wrapper.text()).toContain('9.25');
        await wrapper.findAll('button').find((button) => button.text().includes('Remove coupon')).trigger('click');
        await flushPromises();
        expect(commerce.removeCartCoupon).toHaveBeenCalledOnce();
        setLocale('ar');
        await nextTick();
        expect(wrapper.attributes('dir') || document.documentElement.dir).toBeTruthy();
        expect(wrapper.text()).toContain('الإجمالي التقديري');
    });

    it('renders retryable cart errors and unavailable items without checkout links', async () => {
        commerce.fetchCart.mockRejectedValueOnce({ status: 500 });
        const wrapper = render(CartPage);
        await flushPromises();
        expect(wrapper.text()).toContain('unexpected server error');
        commerce.fetchCart.mockResolvedValue(cartData([{ ...item, available: false }]));
        await wrapper.findAll('button').find((button) => button.text() === 'Try again').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('no longer available');
        expect(wrapper.text()).not.toContain('Proceed to checkout');
    });

    it.each(['course', 'package'])('redirects guest %s purchases to a safe intended detail page', async (type) => {
        useAuthStore().clearSession();
        const wrapper = render(AddToCartButton, { type, product: item.product });
        await wrapper.get('button').trigger('click');
        expect(router.resolve).toHaveBeenCalledWith({ name: type === 'course' ? 'courses.show' : 'packages.show', params: { slug: 'bim' } });
        expect(router.push).toHaveBeenCalledWith({ name: 'login', query: { redirect: '/courses/bim' } });
        expect(commerce.addCartItem).not.toHaveBeenCalled();
    });

    it('prevents duplicate clicks while adding and shows already-present state', async () => {
        commerce.fetchCart.mockResolvedValue(cartData([]));
        const pending = deferred();
        commerce.addCartItem.mockReturnValue(pending.promise);
        const wrapper = render(AddToCartButton, { type: 'course', product: item.product });
        await wrapper.get('button').trigger('click');
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(commerce.addCartItem).toHaveBeenCalledTimes(1);
        pending.resolve(cartData());
        await flushPromises();
        expect(wrapper.text()).toContain('Already in cart');
        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    });

    it('submits required checkout fields once and navigates to the created order', async () => {
        const pending = deferred();
        commerce.checkout.mockReturnValue(pending.promise);
        const wrapper = render(CheckoutPage);
        await flushPromises();
        expect(wrapper.text()).toContain('JOD');
        await wrapper.get('form').trigger('submit');
        await wrapper.get('form').trigger('submit');
        expect(commerce.checkout).toHaveBeenCalledTimes(1);
        expect(commerce.checkout.mock.calls[0][0]).toMatchObject({ customer_name: 'Student', customer_email: 'student@example.test', customer_phone: '0599000000' });
        commerce.fetchCart.mockResolvedValue(cartData([]));
        pending.resolve(order());
        await flushPromises();
        expect(router.push).toHaveBeenCalledWith({ name: 'student.orders.show', params: { id: 12 }, query: { created: '1' } });
    });

    it('shows backend checkout validation errors and preserves the retry UUID after network failure', async () => {
        commerce.checkout.mockRejectedValueOnce({ status: 422, message: 'Invalid', errors: { customer_phone: ['Phone invalid'] } }).mockRejectedValueOnce({ code: 'network' }).mockResolvedValueOnce(order());
        const wrapper = render(CheckoutPage);
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Phone invalid');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('outcome is uncertain');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(commerce.checkout.mock.calls[2][0].idempotency_key).toBe(commerce.checkout.mock.calls[1][0].idempotency_key);
    });

    it('refreshes the cart and explains a checkout pricing conflict before retrying', async () => {
        commerce.checkout.mockRejectedValueOnce({ status: 409, message: 'Pricing changed' });
        commerce.fetchCart.mockResolvedValueOnce(cartData()).mockResolvedValueOnce({ ...cartData(), estimated_total: '12.00' });
        const wrapper = render(CheckoutPage);
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(commerce.checkout.mock.calls[0][0].expected_total).toBe('10.25');
        expect(wrapper.text()).toContain('Pricing changed');
        expect(wrapper.text()).toContain('12.00');
    });

    it('lists only the received orders and paginates using server metadata', async () => {
        commerce.fetchOrders.mockResolvedValue({ items: [order()], meta: { current_page: 1, last_page: 2 } });
        const wrapper = render(OrdersPage);
        await flushPromises();
        expect(wrapper.text()).toContain('JCEC-12');
        expect(wrapper.text()).toContain('Pending payment');
        expect(wrapper.text()).toContain('JOD');
        await wrapper.findAll('button').find((button) => button.text().includes('Next')).trigger('click');
        await flushPromises();
        expect(commerce.fetchOrders).toHaveBeenLastCalledWith(2);
    });

    it('shows order snapshots and protected proof downloads without private paths', async () => {
        const payment = { id: 4, status: 'pending_review', method: 'manual', amount: '10.25', currency: 'JOD', proof_available: true, payment_proof: 'private/never-render.pdf' };
        commerce.fetchOrder.mockResolvedValue(order('awaiting_payment', [payment]));
        const wrapper = render(OrderDetailPage, { id: '12' });
        await flushPromises();
        expect(wrapper.text()).toContain('Historical Package');
        expect(wrapper.text()).toContain('Historical Course');
        expect(wrapper.text()).toContain('180 days');
        expect(wrapper.text()).toContain('Access has not been granted');
        expect(wrapper.html()).not.toContain('private/never-render.pdf');
        expect(wrapper.find('form').exists()).toBe(false);
        await wrapper.findAll('button').find((button) => button.text().includes('Download')).trigger('click');
        expect(commerce.downloadPaymentProof).toHaveBeenCalledWith(4);
    });

    it.each([401, 403, 404])('does not render an owned order after the API denies access (%s)', async (status) => {
        commerce.fetchOrder.mockRejectedValue({ status });
        const wrapper = render(OrderDetailPage, { id: '12' });
        await flushPromises();
        expect(wrapper.text()).not.toContain('Historical Package');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('clears the previous order while navigating to a denied foreign ID', async () => {
        const wrapper = render(OrderDetailPage, { id: '12' });
        await flushPromises();
        commerce.fetchOrder.mockRejectedValue({ status: 404 });
        await wrapper.setProps({ id: '999' });
        await flushPromises();
        expect(wrapper.text()).not.toContain('Historical Package');
    });

    it('validates required proof, file type, and the backend size limit', async () => {
        const wrapper = render(ManualPaymentForm, { order: order() });
        await wrapper.get('form').trigger('submit');
        expect(wrapper.text()).toContain('Select a payment proof');
        await selectFile(wrapper, new File(['x'], 'bad.exe', { type: 'application/octet-stream' }));
        await wrapper.get('form').trigger('submit');
        expect(wrapper.text()).toContain('Use a PDF');
        await selectFile(wrapper, new File([new Uint8Array(1024 * 1024 + 1)], 'huge.pdf', { type: 'application/pdf' }));
        await wrapper.get('form').trigger('submit');
        expect(wrapper.get('#proof-error').text()).toContain('1024 KB');
        expect(commerce.submitPayment).not.toHaveBeenCalled();
    });

    it('uploads multipart proof/reference once with progress and displays pending review', async () => {
        const pending = deferred();
        commerce.submitPayment.mockImplementation((id, form, progress) => { progress({ loaded: 50, total: 100 }); return pending.promise; });
        const wrapper = render(ManualPaymentForm, { order: order() });
        await wrapper.get('#payment-method').setValue('wallet');
        await wrapper.get('#payment-reference').setValue('WALLET-123');
        const file = new File(['proof'], 'proof.pdf', { type: 'application/pdf' });
        await selectFile(wrapper, file);
        await wrapper.get('form').trigger('submit');
        await wrapper.get('form').trigger('submit');
        expect(commerce.submitPayment).toHaveBeenCalledTimes(1);
        const [id, form] = commerce.submitPayment.mock.calls[0];
        expect(id).toBe(12);
        expect(form.get('method')).toBe('wallet');
        expect(form.get('transaction_id')).toBe('WALLET-123');
        expect(form.get('payment_proof')).toBe(file);
        expect(form.has('amount')).toBe(false);
        expect(wrapper.get('progress').attributes('value')).toBe('50');
        pending.resolve({ id: 2, status: 'pending_review' });
        await flushPromises();
        expect(wrapper.text()).toContain('pending review, not access granted');
        expect(wrapper.emitted('submitted')[0][0].status).toBe('pending_review');
    });

    it('renders server proof validation and blocks uncertain upload retries until order refresh', async () => {
        commerce.submitPayment.mockRejectedValueOnce({ status: 422, errors: { payment_proof: ['Corrupt file'] } }).mockRejectedValueOnce({ code: 'network' });
        const wrapper = render(ManualPaymentForm, { order: order() });
        await selectFile(wrapper, new File(['x'], 'proof.pdf', { type: 'application/pdf' }));
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Corrupt file');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.text()).toContain('upload outcome is uncertain');
        expect(wrapper.emitted('refresh')).toHaveLength(1);
    });

    it('keeps an uncertain upload locked when the subsequent order refresh fails', async () => {
        const wrapper = render(OrderDetailPage, { id: '12' });
        await flushPromises();
        commerce.fetchOrder.mockRejectedValue({ status: 500 });
        commerce.submitPayment.mockRejectedValue({ code: 'network' });
        await selectFile(wrapper, new File(['proof'], 'proof.pdf', { type: 'application/pdf' }));
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('upload outcome is uncertain');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(commerce.submitPayment).toHaveBeenCalledTimes(1);
    });

    it('shows protected proof errors without constructing a public storage URL', async () => {
        commerce.fetchOrder.mockResolvedValue(order('awaiting_payment', [{ id: 4, status: 'pending_review', method: 'manual', amount: '10.25', currency: 'JOD', proof_available: true }]));
        commerce.downloadPaymentProof.mockRejectedValue({ status: 403 });
        const wrapper = render(OrderDetailPage, { id: '12' });
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text().includes('Download')).trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('additional permissions');
        expect(wrapper.html()).not.toContain('/storage/');
    });

    it('does not guess a currency for monetary displays', () => {
        const wrapper = render(MoneyAmount, { amount: '10.25', currency: null });
        expect(wrapper.text()).toContain('Currency unavailable');
        expect(wrapper.text()).not.toContain('USD');
    });

    it.each(['paid', 'completed'])('distinguishes approved payment from %s access state', async (status) => {
        commerce.fetchOrder.mockResolvedValue(order(status, [{ id: 2, method: 'bank_transfer', status: 'paid', amount: '10.25', currency: 'JOD' }]));
        const wrapper = render(OrderDetailPage, { id: '12' });
        await flushPromises();
        expect(wrapper.text()).toContain('Approved payment');
        expect(wrapper.text()).toContain(status === 'paid' ? 'provisioning is not confirmed' : 'access was provisioned');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('renders rejected proof and allows resubmission only when order permits', async () => {
        commerce.fetchOrder.mockResolvedValue(order('pending', [{ id: 2, method: 'manual', status: 'rejected', amount: '10.25', currency: 'JOD', rejection_reason: 'Unreadable proof' }]));
        const wrapper = render(OrderDetailPage, { id: '12' });
        await flushPromises();
        expect(wrapper.text()).toContain('Rejected');
        expect(wrapper.text()).toContain('Unreadable proof');
        expect(wrapper.find('form').exists()).toBe(true);
        expect(canSubmitPayment(order('cancelled'))).toBe(false);
        expect(accessState(order('paid'))).toBe('paid');
    });

    it.each(['ar', 'en'])('renders every commerce status and currency in %s with correct direction', (locale) => {
        setLocale(locale);
        expect(document.documentElement.dir).toBe(locale === 'ar' ? 'rtl' : 'ltr');
        for (const [kind, statuses] of [['order', orderStatuses], ['payment', paymentStatuses]]) {
            for (const status of statuses) {
                const wrapper = render(CommerceStatus, { kind, status });
                expect(wrapper.text()).toBe(i18n.global.t(`commerce.${kind}Statuses.${status}`));
                wrapper.unmount();
            }
        }
        const wrapper = render(MoneyAmount, { amount: '9999999999.99', currency: 'JOD' });
        expect(wrapper.text()).toContain('JOD');
        expect(wrapper.text()).toContain(moneyText('9999999999.99', locale));
    });
});
