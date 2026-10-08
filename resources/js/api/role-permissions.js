import { api } from './client';

const base = '/api/v1/admin/roles';

export async function fetchRolePermissionMatrix() {
    return (await api.get(base)).data;
}

export async function fetchRolePermissions(role) {
    return (await api.get(`${base}/${encodeURIComponent(role)}`)).data;
}

export async function saveRolePermissions(role, permissions, version) {
    return (await api.put(`${base}/${encodeURIComponent(role)}/permissions`, { permissions, version })).data;
}
