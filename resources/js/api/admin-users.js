import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/admin/users';
export async function fetchAdminUsers(params = {}) { return unwrapCollection(await api.get(base, { params })); }
export async function fetchAdminUser(id) { return unwrapResource(await api.get(`${base}/${id}`)); }
export async function createAdminUser(payload) { return unwrapResource(await api.post(base, payload)); }
export async function updateAdminUser(id, payload) { return unwrapResource(await api.patch(`${base}/${id}`, payload)); }
