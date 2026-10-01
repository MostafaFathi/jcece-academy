import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/admin/instructors';
export async function fetchAdminInstructors(params = {}) { return unwrapCollection(await api.get(base, { params })); }
export async function fetchAdminInstructor(id) { return unwrapResource(await api.get(`${base}/${id}`)); }
export async function createAdminInstructor(payload) { return unwrapResource(await api.post(base, payload)); }
export async function updateAdminInstructor(id, payload) { return unwrapResource(await api.patch(`${base}/${id}`, payload)); }
