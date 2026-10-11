import { api } from './client';
export const fetchNotifications = async (params = {}) => (await api.get('/api/v1/notifications', { params })).data;
export const readNotification = (id) => api.post(`/api/v1/notifications/${id}/read`);
export const readAllNotifications = () => api.post('/api/v1/notifications/read-all');
export const openNotification = async (id) => (await api.post(`/api/v1/notifications/${id}/open`)).data;
export const fetchPreferences = async () => (await api.get('/api/v1/notification-preferences')).data.data;
export const savePreferences = async (preferences) => (await api.put('/api/v1/notification-preferences', { preferences })).data.data;
export const fetchAnnouncement = async (id) => (await api.get(`/api/v1/announcements/${id}`)).data.data;
export const fetchManagement = async (page = 1, legacyPage = 1) => (await api.get('/api/v1/admin/notification-management', { params: { page, legacy_page: legacyPage } })).data;
export const retryTransactionalDelivery = (id) => api.post(`/api/v1/admin/notification-management/transactional-deliveries/${id}/retry`);
export const savePolicy = (policy) => api.put('/api/v1/admin/notification-management/policies', policy);
export const retryDelivery = (id) => api.post(`/api/v1/admin/notification-management/deliveries/${id}/retry`);
export const fetchStaffAnnouncements = async (page = 1) => (await api.get('/api/v1/admin/announcements', { params: { page } })).data;
export const saveAnnouncement = async (data, id = null) => (await (id ? api.put(`/api/v1/admin/announcements/${id}`, data) : api.post('/api/v1/admin/announcements', data))).data.data;
export const previewAudience = async (data) => (await api.post('/api/v1/admin/announcements/preview', data)).data;
export const publishAnnouncement = (id) => api.post(`/api/v1/admin/announcements/${id}/publish`);
export const archiveAnnouncement = (id) => api.post(`/api/v1/admin/announcements/${id}/archive`);
export function notifyInboxChanged() { window.dispatchEvent(new Event('notifications:changed')); }
export function safeNotificationPath(path) { return typeof path === 'string' && path.startsWith('/') && !path.startsWith('//') && !path.includes('\\'); }
