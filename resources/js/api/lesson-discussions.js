import { api } from './client';
import { unwrapResource } from './responses';

const url = (slug, lessonId) => `/api/v1/me/courses/${encodeURIComponent(slug)}/lessons/${encodeURIComponent(lessonId)}/comments`;

export async function fetchLessonComments(slug, lessonId, before = null) {
    const response = await api.get(url(slug, lessonId), { params: before ? { before } : {} });
    return response.data;
}

export async function postLessonComment(slug, lessonId, payload) {
    return unwrapResource(await api.post(url(slug, lessonId), payload));
}

export async function fetchLessonReplies(slug, lessonId, commentId, after = null) {
    const response = await api.get(`${url(slug, lessonId)}/${encodeURIComponent(commentId)}/replies`, { params: after ? { after } : {} });
    return response.data;
}
