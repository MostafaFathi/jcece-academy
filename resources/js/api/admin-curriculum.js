import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/admin';
export async function fetchSections(courseId) { return unwrapCollection(await api.get(`${base}/courses/${courseId}/sections`)).items; }
export async function createSection(courseId, payload) { return unwrapResource(await api.post(`${base}/courses/${courseId}/sections`, payload)); }
export async function updateSection(courseId, sectionId, payload) { return unwrapResource(await api.patch(`${base}/courses/${courseId}/sections/${sectionId}`, payload)); }
export async function deleteSection(courseId, sectionId) { await api.delete(`${base}/courses/${courseId}/sections/${sectionId}`); }
export async function reorderSections(courseId, ids) { return unwrapCollection(await api.post(`${base}/courses/${courseId}/sections/reorder`, { ids })).items; }

export async function createLesson(sectionId, payload) { return unwrapResource(await api.post(`${base}/sections/${sectionId}/lessons`, payload)); }
export async function updateLesson(sectionId, lessonId, payload) { return unwrapResource(await api.patch(`${base}/sections/${sectionId}/lessons/${lessonId}`, payload)); }
export async function deleteLesson(sectionId, lessonId) { await api.delete(`${base}/sections/${sectionId}/lessons/${lessonId}`); }
export async function reorderLessons(sectionId, ids) { return unwrapCollection(await api.post(`${base}/sections/${sectionId}/lessons/reorder`, { ids })).items; }

export async function fetchLessonResources(lessonId) { return unwrapCollection(await api.get(`${base}/lessons/${lessonId}/resources`)).items; }
export async function createLessonResource(lessonId, payload) { return unwrapResource(await api.post(`${base}/lessons/${lessonId}/resources`, payload)); }
export async function updateLessonResource(lessonId, resourceId, payload) { return unwrapResource(await api.patch(`${base}/lessons/${lessonId}/resources/${resourceId}`, payload)); }
export async function deleteLessonResource(lessonId, resourceId) { await api.delete(`${base}/lessons/${lessonId}/resources/${resourceId}`); }
export async function reorderLessonResources(lessonId, ids) { return unwrapCollection(await api.post(`${base}/lessons/${lessonId}/resources/reorder`, { ids })).items; }
