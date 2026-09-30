import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

export async function fetchCourseReviews(slug, params = {}) {
    return unwrapCollection(await api.get(`api/v1/courses/${encodeURIComponent(slug)}/reviews`, { params }));
}

const ownCourseUrl = (slug) => `/api/v1/me/courses/${encodeURIComponent(slug)}`;
export async function fetchMyReview(slug) { return unwrapResource(await api.get(`${ownCourseUrl(slug)}/review`)); }
export async function createReview(slug, payload) { return unwrapResource(await api.post(`${ownCourseUrl(slug)}/reviews`, payload)); }
export async function updateReview(id, payload) { return unwrapResource(await api.patch(`/api/v1/me/reviews/${id}`, payload)); }
