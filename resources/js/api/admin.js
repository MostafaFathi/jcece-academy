import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/admin';
export async function fetchAdminCategories(page = 1) { return unwrapCollection(await api.get(`${base}/categories`, { params: { page } })); }
export async function fetchAdminCategory(id) { return unwrapResource(await api.get(`${base}/categories/${id}`)); }
export async function createAdminCategory(payload) { return unwrapResource(await api.post(`${base}/categories`, payload)); }
export async function updateAdminCategory(id, payload) { return unwrapResource(await api.patch(`${base}/categories/${id}`, payload)); }
export async function deleteAdminCategory(id) { await api.delete(`${base}/categories/${id}`); }
export async function fetchAdminCourses(params = {}) { return unwrapCollection(await api.get(`${base}/courses`, { params })); }
export async function fetchAdminCourse(id) { return unwrapResource(await api.get(`${base}/courses/${id}`)); }
export async function updateAdminCourse(id, payload) { return unwrapResource(await api.patch(`${base}/courses/${id}`, payload)); }
export async function deleteAdminCourse(id) { await api.delete(`${base}/courses/${id}`); }
