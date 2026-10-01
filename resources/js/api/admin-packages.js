import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/admin/packages';
export async function fetchAdminPackages(params = {}) { return unwrapCollection(await api.get(base, { params })); }
export async function fetchAdminPackage(id) { return unwrapResource(await api.get(`${base}/${id}`)); }
export async function createAdminPackage(payload) { return unwrapResource(await api.post(base, payload)); }
export async function updateAdminPackage(id, payload) { return unwrapResource(await api.patch(`${base}/${id}`, payload)); }
export async function deleteAdminPackage(id) { await api.delete(`${base}/${id}`); }
export async function fetchPackageCourses(id) { return unwrapCollection(await api.get(`${base}/${id}/courses`)).items; }
export async function addPackageCourse(id, payload) { return unwrapResource(await api.post(`${base}/${id}/courses`, payload)); }
export async function updatePackageCourse(id, membershipId, payload) { return unwrapResource(await api.patch(`${base}/${id}/courses/${membershipId}`, payload)); }
export async function removePackageCourse(id, membershipId) { await api.delete(`${base}/${id}/courses/${membershipId}`); }
export async function reorderPackageCourses(id, ids) { return unwrapCollection(await api.post(`${base}/${id}/courses/reorder`, { ids })).items; }
