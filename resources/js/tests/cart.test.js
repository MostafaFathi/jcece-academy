import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';
import { useCartStore } from '../stores/cart';
import { useAuthStore } from '../stores/auth';
import * as commerce from '../api/commerce';

vi.mock('../api/commerce', () => ({ fetchCart: vi.fn(), addCartItem: vi.fn(), removeCartItem: vi.fn(), clearCart: vi.fn(), checkout: vi.fn() }));
const product = { id: 4, title: 'Server course', slug: 'server-course', price: '10.25', access_duration_days: 90 };
const item = { id: 8, purchasable_type: 'course', purchasable_id: 4, available: true, product };
const snapshot = (items = [item], total = '10.25', currency = 'JOD') => ({ id: 1, items, item_count: items.length, estimated_total: total, currency });
const customer = { customer_name: 'Student', customer_email: 'student@example.test', customer_phone: '+970599000000', notes: '' };
function deferred() { let resolve; const promise = new Promise((done) => { resolve = done; }); return { promise, resolve }; }

describe('server-backed cart and checkout intent', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        sessionStorage.clear();
        setActivePinia(createPinia());
        useAuthStore().setUser({ id: 1, ...customer });
        commerce.fetchCart.mockResolvedValue(snapshot());
    });

    it('maps the real resource without recalculating totals', async () => {
        const cart = useCartStore();
        commerce.fetchCart.mockResolvedValue(snapshot([item], '123.45', 'EUR'));
        await cart.load();
        expect(cart.items[0].product.access_duration_days).toBe(90);
        expect(cart.total).toBe('123.45');
        expect(cart.currency).toBe('EUR');
        expect(cart.count).toBe(1);
        expect(cart.ready).toBe(true);
    });

    it('shows loading and shares concurrent retrieval', async () => {
        const pending = deferred();
        commerce.fetchCart.mockReturnValue(pending.promise);
        const cart = useCartStore();
        const first = cart.load();
        const second = cart.load();
        expect(cart.loading).toBe(true);
        expect(commerce.fetchCart).toHaveBeenCalledTimes(1);
        pending.resolve(snapshot([], '0.00'));
        await Promise.all([first, second]);
        expect(cart.loading).toBe(false);
        expect(cart.count).toBe(0);
        expect(cart.ready).toBe(false);
    });

    it.each(['course', 'package'])('adds %s using the actual payload and server response', async (type) => {
        commerce.addCartItem.mockResolvedValue(snapshot([{ ...item, purchasable_type: type }]));
        const cart = useCartStore();
        await cart.add(type, 4);
        expect(commerce.addCartItem).toHaveBeenCalledWith(type, 4);
        expect(cart.hasItem(type, 4)).toBe(true);
        expect(cart.total).toBe('10.25');
    });

    it('blocks rapid duplicate additions and already-present items', async () => {
        const pending = deferred();
        commerce.addCartItem.mockReturnValue(pending.promise);
        const cart = useCartStore();
        const first = cart.add('course', 4);
        expect(await cart.add('course', 4)).toBeNull();
        pending.resolve(snapshot());
        await first;
        await cart.add('course', 4);
        expect(commerce.addCartItem).toHaveBeenCalledTimes(1);
    });

    it('removes and clears through server resources', async () => {
        commerce.removeCartItem.mockResolvedValue(snapshot([], '0.00'));
        commerce.clearCart.mockResolvedValue(snapshot([], '0.00'));
        const cart = useCartStore();
        await cart.load();
        await cart.remove(8);
        expect(commerce.removeCartItem).toHaveBeenCalledWith(8);
        expect(cart.total).toBe('0.00');
        await cart.clear();
        expect(commerce.clearCart).toHaveBeenCalledTimes(1);
    });

    it('retains mutation errors and does not turn unavailable items into valid checkout', async () => {
        const error = { status: 422, errors: { purchasable_id: ['Unavailable'] } };
        commerce.addCartItem.mockRejectedValue(error);
        const cart = useCartStore();
        await expect(cart.add('course', 4)).rejects.toEqual(error);
        expect(cart.error).toEqual(error);
        commerce.fetchCart.mockResolvedValue(snapshot([{ ...item, available: false }]));
        await cart.load();
        expect(cart.ready).toBe(false);
    });

    it('fails closed for malformed or missing-currency responses', async () => {
        const cart = useCartStore();
        commerce.fetchCart.mockResolvedValue({ items: [], item_count: 7, estimated_total: '0.00', currency: 'JOD' });
        await expect(cart.load()).rejects.toThrow('Invalid cart');
        expect(cart.cart).toBeNull();
        commerce.fetchCart.mockResolvedValue(snapshot([item], '10.25', null));
        await cart.load();
        expect(cart.ready).toBe(false);
    });

    it('reuses both UUID and payload after an uncertain retry and synchronizes the cleared cart', async () => {
        const cart = useCartStore();
        commerce.checkout.mockRejectedValueOnce({ code: 'network' }).mockResolvedValueOnce({ id: 11 });
        await expect(cart.placeOrder(customer)).rejects.toEqual({ code: 'network' });
        const original = commerce.checkout.mock.calls[0][0];
        expect(original.idempotency_key).toMatch(/^[a-f0-9-]{36}$/);
        expect(cart.uncertain).toBe(true);
        expect(sessionStorage.getItem('jcec.checkout-intent')).toBe(original.idempotency_key);
        expect(JSON.stringify(sessionStorage)).not.toContain(customer.customer_email);
        expect(await cart.add('course', 4)).toBeNull();
        commerce.fetchCart.mockResolvedValue(snapshot([], '0.00'));
        expect(await cart.placeOrder({ ...customer, customer_name: 'Changed' })).toEqual({ id: 11 });
        expect(commerce.checkout.mock.calls[1][0]).toEqual(original);
        expect(cart.count).toBe(0);
        expect(cart.checkoutKey).toBeNull();
        expect(sessionStorage.getItem('jcec.checkout-intent')).toBeNull();
    });

    it('keeps the UUID on validation failure while allowing corrected customer fields', async () => {
        const cart = useCartStore();
        commerce.checkout.mockRejectedValueOnce({ status: 422 }).mockResolvedValueOnce({ id: 1 });
        await expect(cart.placeOrder(customer)).rejects.toEqual({ status: 422 });
        await cart.placeOrder({ ...customer, customer_name: 'Corrected' });
        expect(commerce.checkout.mock.calls[1][0].idempotency_key).toBe(commerce.checkout.mock.calls[0][0].idempotency_key);
        expect(commerce.checkout.mock.calls[1][0].customer_name).toBe('Corrected');
    });

    it('blocks duplicate checkout and rotates UUID only after success or explicit new intent', async () => {
        const pending = deferred();
        const cart = useCartStore();
        commerce.checkout.mockReturnValueOnce(pending.promise);
        const first = cart.placeOrder(customer);
        const oldKey = cart.checkoutKey;
        expect(await cart.placeOrder(customer)).toBeNull();
        cart.newIntent();
        expect(cart.checkoutKey).toBe(oldKey);
        pending.resolve({ id: 1 });
        await first;
        commerce.checkout.mockRejectedValueOnce({ status: 500 });
        await expect(cart.placeOrder(customer)).rejects.toEqual({ status: 500 });
        expect(cart.checkoutKey).not.toBe(oldKey);
        cart.newIntent();
        expect(cart.uncertain).toBe(false);
        expect(cart.checkoutKey).toBeNull();
    });

    it('retains only the UUID across remount/reload', async () => {
        sessionStorage.setItem('jcec.checkout-intent', '123e4567-e89b-42d3-a456-426614174000');
        const cart = useCartStore();
        commerce.checkout.mockResolvedValue({ id: 3 });
        expect(cart.uncertain).toBe(true);
        await cart.placeOrder(customer);
        expect(commerce.checkout.mock.calls[0][0].idempotency_key).toBe('123e4567-e89b-42d3-a456-426614174000');
    });

    it('clears private state on logout and ignores an in-flight response', async () => {
        const pending = deferred();
        commerce.fetchCart.mockReturnValue(pending.promise);
        const cart = useCartStore();
        const load = cart.load();
        useAuthStore().clearSession();
        await nextTick();
        pending.resolve(snapshot());
        await load;
        expect(cart.cart).toBeNull();
        expect(cart.error).toBeNull();
        expect(cart.loading).toBe(false);
    });
});
