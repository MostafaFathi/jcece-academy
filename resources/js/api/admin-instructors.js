import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/admin/instructors';
function instructorFormData(payload) {
    const formData = new FormData();
    for (const [key, value] of Object.entries(payload)) {
        if (Array.isArray(value)) {
            if (key === 'specialties' && value.length === 0) formData.append('clear_specialties', '1');
            value.forEach((item, index) => formData.append(`${key}[${index}]`, item));
        } else if (value !== undefined) {
            formData.append(key, value === null ? '' : typeof value === 'boolean' ? (value ? '1' : '0') : value);
        }
    }
    return formData;
}
export async function fetchAdminInstructors(params = {}) { return unwrapCollection(await api.get(base, { params })); }
export async function fetchAdminInstructor(id) { return unwrapResource(await api.get(`${base}/${id}`)); }
export async function createAdminInstructor(payload) { return unwrapResource(await api.post(base, payload.avatar ? instructorFormData(payload) : payload)); }
export async function updateAdminInstructor(id, payload) {
    if (payload.avatar) {
        const formData = instructorFormData(payload);
        formData.append('_method', 'PATCH');
        return unwrapResource(await api.post(`${base}/${id}`, formData));
    }
    return unwrapResource(await api.patch(`${base}/${id}`, payload));
}
