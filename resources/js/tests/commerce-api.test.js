import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api, downloadBlob } from '../api/client';
import * as commerce from '../api/commerce';
vi.mock('../api/client', () => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }, downloadBlob: vi.fn() }));
describe('commerce endpoint adapters', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        api.get.mockResolvedValue({ data: { data: { id: 1 } } });
        api.post.mockResolvedValue({ data: { data: { id: 1 } } });
        api.put.mockResolvedValue({ data: { data: { id: 1 } } });
        api.delete.mockResolvedValue({ data: { data: { id: 1 } } });
    });
    it('uses actual cart endpoints and minimal purchase payloads', async () => {
        expect(await commerce.fetchCart()).toEqual({ id: 1 });
        await commerce.addCartItem('package', 3);
        await commerce.removeCartItem(4);
        await commerce.clearCart();
        expect(api.get).toHaveBeenCalledWith('/api/v1/me/cart');
        expect(api.post).toHaveBeenCalledWith('/api/v1/me/cart/items', { purchasable_type: 'package', purchasable_id: 3 });
        expect(api.delete.mock.calls).toEqual([['/api/v1/me/cart/items/4'], ['/api/v1/me/cart']]);
    });
    it('unwraps paginated orders and detail/checkout responses', async () => {
        api.get.mockResolvedValueOnce({ data: { data: [{ id: 7 }], meta: { current_page: 2 } } });
        expect(await commerce.fetchOrders(2)).toEqual({ items: [{ id: 7 }], meta: { current_page: 2 }, links: {} });
        expect(api.get).toHaveBeenCalledWith('/api/v1/me/orders', { params: { page: 2 } });
        await commerce.fetchOrder(7);
        await commerce.checkout({ idempotency_key: 'uuid' });
        expect(api.get).toHaveBeenLastCalledWith('/api/v1/me/orders/7');
        expect(api.post).toHaveBeenCalledWith('/api/v1/me/checkout', { idempotency_key: 'uuid' });
    });
    it('uses scoped cart coupon apply and remove endpoints', async () => {
        await commerce.applyCartCoupon('SAVE10');
        await commerce.removeCartCoupon();
        expect(api.put).toHaveBeenCalledWith('/api/v1/me/cart/coupon', { code: 'SAVE10' });
        expect(api.delete).toHaveBeenCalledWith('/api/v1/me/cart/coupon');
    });
    it('passes FormData to the authenticated client and uses the private blob helper', async () => {
        const form = new FormData();
        const progress = vi.fn();
        await commerce.submitPayment(7, form, progress);
        await commerce.downloadPaymentProof(9);
        expect(api.post).toHaveBeenCalledWith('/api/v1/me/orders/7/payments', form, { onUploadProgress: progress });
        expect(downloadBlob).toHaveBeenCalledWith('/api/v1/me/payments/9/proof', 'payment-proof-9');
    });
});
