import { api } from './client';

export async function fetchPolicyPage(slug, locale) {
    return (await api.get(`/api/v1/policies/${slug}`, { params: { locale } })).data.data;
}

export async function fetchAdminPolicyPages() {
    return (await api.get('/api/v1/admin/policy-pages')).data.data;
}

export async function savePolicyPage(slug, payload) {
    return (await api.put(`/api/v1/admin/policy-pages/${slug}`, payload)).data.data;
}

export async function publishPolicyPage(slug) {
    return (await api.post(`/api/v1/admin/policy-pages/${slug}/publication`)).data.data;
}
