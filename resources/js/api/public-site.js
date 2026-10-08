import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

export async function fetchInstructors(params = {}) {
    return unwrapCollection(await api.get('api/v1/instructors', { params }));
}

export async function fetchInstructor(id) {
    return unwrapResource(await api.get(`api/v1/instructors/${encodeURIComponent(id)}`));
}

export async function fetchSitePage(slug) {
    return unwrapResource(await api.get(`api/v1/site-pages/${encodeURIComponent(slug)}`));
}

export async function fetchSiteFaqs() {
    return unwrapResource(await api.get('api/v1/site-faqs'));
}

export async function fetchTestimonials() {
    return unwrapCollection(await api.get('api/v1/testimonials'));
}

export async function submitContact(payload) {
    return (await api.post('api/v1/contact', payload)).data;
}

export async function fetchAdminSiteContent() {
    return unwrapResource(await api.get('api/v1/admin/site-content'));
}

export async function saveSitePage(slug, payload) {
    return unwrapResource(await api.put(`api/v1/admin/site-pages/${encodeURIComponent(slug)}`, payload));
}

export async function publishSitePage(slug) {
    return unwrapResource(await api.post(`api/v1/admin/site-pages/${encodeURIComponent(slug)}/publication`));
}

export async function saveSiteFaq(id, payload) {
    return unwrapResource(await (id ? api.patch(`api/v1/admin/site-faqs/${id}`, payload) : api.post('api/v1/admin/site-faqs', payload)));
}

export async function deleteSiteFaq(id) {
    await api.delete(`api/v1/admin/site-faqs/${id}`);
}

export async function fetchContactMessages(params = {}) {
    return (await api.get('api/v1/admin/contact-messages', { params })).data;
}

export async function fetchCourseFaqs(courseId) {
    return unwrapResource(await api.get(`api/v1/admin/courses/${courseId}/faqs`));
}

export async function saveCourseFaq(courseId, id, payload) {
    return unwrapResource(await (id ? api.patch(`api/v1/admin/courses/${courseId}/faqs/${id}`, payload) : api.post(`api/v1/admin/courses/${courseId}/faqs`, payload)));
}

export async function deleteCourseFaq(courseId, id) {
    await api.delete(`api/v1/admin/courses/${courseId}/faqs/${id}`);
}
