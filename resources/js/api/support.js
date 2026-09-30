import { api, downloadBlob } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/me/support-tickets';
export async function fetchTickets(page = 1) { return unwrapCollection(await api.get(base, { params: { page } })); }
export async function createTicket(form) { return unwrapResource(await api.post(base, form)); }
export async function fetchTicket(id) { return unwrapResource(await api.get(`${base}/${id}`)); }
export async function fetchTicketMessages(id, page = 1) { return unwrapCollection(await api.get(`${base}/${id}/messages`, { params: { page } })); }
export async function replyToTicket(id, form) { return unwrapResource(await api.post(`${base}/${id}/messages`, form)); }
export async function reopenTicket(id) { return unwrapResource(await api.post(`${base}/${id}/reopen`)); }
export function downloadTicketAttachment(id) { return downloadBlob(`/api/v1/me/support-ticket-attachments/${id}/download`, `support-attachment-${id}`); }
