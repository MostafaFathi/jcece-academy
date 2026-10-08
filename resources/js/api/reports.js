import { api, downloadBlob } from './client';

export async function fetchReport(scope, type, filters = {}) {
    const response = await api.get(`/api/v1/${scope}/reports/${type}`, { params: filters });
    return response.data;
}

export async function exportReport(type, format, filters = {}) {
    const query = new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined));
    await downloadBlob(`/api/v1/admin/reports/${type}/export/${format}?${query}`, `jcec-${type}.${format}`);
}
