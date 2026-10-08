import { api, downloadBlob } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/me';

export async function fetchCart() { return unwrapResource(await api.get(`${base}/cart`)); }
export async function addCartItem(type, id) { return unwrapResource(await api.post(`${base}/cart/items`, { purchasable_type: type, purchasable_id: id })); }
export async function removeCartItem(id) { return unwrapResource(await api.delete(`${base}/cart/items/${id}`)); }
export async function clearCart() { return unwrapResource(await api.delete(`${base}/cart`)); }
export async function applyCartCoupon(code) { return unwrapResource(await api.put(`${base}/cart/coupon`, { code })); }
export async function removeCartCoupon() { return unwrapResource(await api.delete(`${base}/cart/coupon`)); }
export async function checkout(payload) { return unwrapResource(await api.post(`${base}/checkout`, payload)); }
export async function fetchOrders(page = 1) { return unwrapCollection(await api.get(`${base}/orders`, { params: { page } })); }
export async function fetchOrder(id) { return unwrapResource(await api.get(`${base}/orders/${id}`)); }
export async function issueOrderDocuments(id) { return unwrapCollection(await api.post(`${base}/orders/${id}/financial-documents`)); }
export function downloadOrderDocument(id) { return downloadBlob(`${base}/financial-documents/${id}/download`, `jcec-receipt-${id}.pdf`); }
export async function submitPayment(id, form, onUploadProgress) {
    return unwrapResource(await api.post(`${base}/orders/${id}/payments`, form, { onUploadProgress }));
}
export function downloadPaymentProof(id) { return downloadBlob(`${base}/payments/${id}/proof`, `payment-proof-${id}`); }
