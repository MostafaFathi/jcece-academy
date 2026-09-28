import { api } from './client';
import { unwrapCollection } from './responses';

export async function fetchCourseReviews(slug, params = {}) {
    return unwrapCollection(await api.get(`api/v1/courses/${encodeURIComponent(slug)}/reviews`, { params }));
}
