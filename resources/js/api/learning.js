import { api, downloadBlob } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const courseUrl = (slug) => `/api/v1/me/courses/${encodeURIComponent(slug)}`;
export async function fetchMyCourses(page = 1) { return unwrapCollection(await api.get('/api/v1/me/courses', { params: { page } })); }
export async function fetchLearningCourse(slug) { return unwrapResource(await api.get(`${courseUrl(slug)}/learn`)); }
export async function fetchCourseProgress(slug) { return unwrapResource(await api.get(`${courseUrl(slug)}/progress`)); }
export async function completeLesson(slug, id) { return unwrapResource(await api.post(`${courseUrl(slug)}/lessons/${id}/complete`)); }
export async function saveLessonProgress(slug, id, payload) { return unwrapResource(await api.patch(`${courseUrl(slug)}/lessons/${id}/progress`, payload)); }
export function downloadLessonResource(slug, lessonId, resourceId) { return downloadBlob(`${courseUrl(slug)}/lessons/${lessonId}/resources/${resourceId}/download`, `lesson-resource-${resourceId}`); }
