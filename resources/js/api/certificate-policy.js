import { api } from './client';
import { unwrapResource } from './responses';

const admin = '/api/v1/admin';

export const fetchCourseCertificateRequirements = async (id) => unwrapResource(await api.get(`${admin}/courses/${id}/certificate-requirements`));
export const saveCourseCertificateRequirements = async (id, payload) => unwrapResource(await api.put(`${admin}/courses/${id}/certificate-requirements`, payload));
export const fetchCertificateApprovalRequests = async (params = {}) => (await api.get(`${admin}/certificate-approval-requests`, { params })).data;
export const approveCertificateRequest = async (id) => unwrapResource(await api.post(`${admin}/certificate-approval-requests/${id}/approve`));
export const requestCertificateApproval = async (slug) => unwrapResource(await api.post(`/api/v1/me/courses/${encodeURIComponent(slug)}/certificate-approval-request`));
