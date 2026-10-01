import { api, downloadBlob } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/admin';
export async function fetchOperationalOrders(params = {}) { return unwrapCollection(await api.get(`${base}/orders`, { params })); }
export async function fetchOperationalOrder(id) { return unwrapResource(await api.get(`${base}/orders/${id}`)); }
export async function provisionOrderAccess(id) { return unwrapResource(await api.post(`${base}/orders/${id}/provision-access`)); }
export async function fetchOperationalPayments(params = {}) { return unwrapCollection(await api.get(`${base}/payments`, { params })); }
export async function fetchOperationalPayment(id) { return unwrapResource(await api.get(`${base}/payments/${id}`)); }
export function downloadOperationalProof(id) { return downloadBlob(`${base}/payments/${id}/proof`, `payment-proof-${id}`); }
export async function approveOperationalPayment(id) { return unwrapResource(await api.post(`${base}/payments/${id}/approve`)); }
export async function rejectOperationalPayment(id, reason) { return unwrapResource(await api.post(`${base}/payments/${id}/reject`, { rejection_reason: reason })); }

export async function fetchModerationReviews(params = {}) { return unwrapCollection(await api.get(`${base}/course-reviews`, { params })); }
export async function fetchModerationReview(id) { return unwrapResource(await api.get(`${base}/course-reviews/${id}`)); }
export async function publishModerationReview(id) { return unwrapResource(await api.post(`${base}/course-reviews/${id}/publication`)); }
export async function rejectModerationReview(id, reason) { return unwrapResource(await api.post(`${base}/course-reviews/${id}/rejection`, { reason })); }
export async function hideModerationReview(id, reason) { return unwrapResource(await api.post(`${base}/course-reviews/${id}/hiding`, { reason })); }

export async function fetchOperationalCertificates(params = {}) { return unwrapCollection(await api.get(`${base}/certificates`, { params })); }
export async function fetchOperationalCertificate(id) { return unwrapResource(await api.get(`${base}/certificates/${id}`)); }
export function downloadOperationalCertificate(id) { return downloadBlob(`${base}/certificates/${id}/download`, `certificate-${id}.pdf`); }
export async function revokeOperationalCertificate(id, reason) { return unwrapResource(await api.post(`${base}/certificates/${id}/revoke`, { reason })); }
export async function reissueOperationalCertificate(id) { return unwrapResource(await api.post(`${base}/certificates/${id}/reissue`)); }

export async function fetchOperationalTickets(params = {}) { return unwrapCollection(await api.get(`${base}/support-tickets`, { params })); }
export async function fetchOperationalTicket(id) { return unwrapResource(await api.get(`${base}/support-tickets/${id}`)); }
export async function fetchOperationalTicketMessages(id, page = 1) { return unwrapCollection(await api.get(`${base}/support-tickets/${id}/messages`, { params: { page } })); }
export async function updateOperationalTicket(id, payload) { return unwrapResource(await api.patch(`${base}/support-tickets/${id}`, payload)); }
export async function assignOperationalTicket(id, payload) { return unwrapResource(await api.put(`${base}/support-tickets/${id}/assignment`, payload)); }
export async function postOperationalTicketMessage(id, form) { return unwrapResource(await api.post(`${base}/support-tickets/${id}/messages`, form)); }
export function downloadOperationalAttachment(id) { return downloadBlob(`${base}/support-ticket-attachments/${id}/download`, `support-attachment-${id}`); }
