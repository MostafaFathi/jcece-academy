import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';
import { catalogMediaPayload } from './catalog-media';

const base = '/api/v1/admin';
export async function fetchAdminCategories(page = 1) { return unwrapCollection(await api.get(`${base}/categories`, { params: { page } })); }
export async function fetchAdminCategory(id) { return unwrapResource(await api.get(`${base}/categories/${id}`)); }
export async function createAdminCategory(payload) { return unwrapResource(await api.post(`${base}/categories`, catalogMediaPayload(payload, 'image_upload'))); }
export async function updateAdminCategory(id, payload) { return unwrapResource(await (payload.image_upload ? api.post(`${base}/categories/${id}`, catalogMediaPayload(payload, 'image_upload', true)) : api.patch(`${base}/categories/${id}`, payload))); }
export async function deleteAdminCategory(id) { await api.delete(`${base}/categories/${id}`); }
export async function fetchAdminCourses(params = {}) { return unwrapCollection(await api.get(`${base}/courses`, { params })); }
export async function fetchAdminCourse(id) { return unwrapResource(await api.get(`${base}/courses/${id}`)); }
export async function updateAdminCourse(id, payload) { return unwrapResource(await (payload.thumbnail_upload ? api.post(`${base}/courses/${id}`, catalogMediaPayload(payload, 'thumbnail_upload', true)) : api.patch(`${base}/courses/${id}`, payload))); }
export async function deleteAdminCourse(id) { await api.delete(`${base}/courses/${id}`); }
export async function createAdminCourse(payload) { return unwrapResource(await api.post(`${base}/courses`, catalogMediaPayload(payload, 'thumbnail_upload'))); }
export async function fetchAdminDashboardSummary() { return unwrapResource(await api.get(`${base}/dashboard-summary`)); }
