import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

export async function fetchCourses(params = {}) {
    return unwrapCollection(await api.get('api/v1/courses', { params }));
}

export async function fetchCourse(slug) {
    return unwrapResource(await api.get(`api/v1/courses/${encodeURIComponent(slug)}`));
}
