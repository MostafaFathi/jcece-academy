import { api, downloadBlob } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/admin';
const quizUrl = (courseId, quizId) => `${base}/courses/${courseId}/quizzes/${quizId}`;
const assignmentUrl = (courseId, assignmentId) => `${base}/courses/${courseId}/assignments/${assignmentId}`;

export async function listCourseQuizzes(courseId) { return unwrapCollection(await api.get(`${base}/courses/${courseId}/quizzes`)); }
export async function getQuiz(courseId, quizId) { return unwrapResource(await api.get(quizUrl(courseId, quizId))); }
export async function createQuiz(courseId, payload) { return unwrapResource(await api.post(`${base}/courses/${courseId}/quizzes`, payload)); }
export async function updateQuiz(courseId, quizId, payload) { return unwrapResource(await api.patch(quizUrl(courseId, quizId), payload)); }
export async function archiveQuiz(courseId, quizId) { return unwrapResource(await api.delete(quizUrl(courseId, quizId))); }
export async function publishQuiz(quizId) { return unwrapResource(await api.post(`${base}/quizzes/${quizId}/publication`)); }
export async function unpublishQuiz(quizId) { return unwrapResource(await api.delete(`${base}/quizzes/${quizId}/publication`)); }
export async function addQuestion(quizId, payload) { return unwrapResource(await api.post(`${base}/quizzes/${quizId}/questions`, payload)); }
export async function updateQuestion(quizId, questionId, payload) { return unwrapResource(await api.put(`${base}/quizzes/${quizId}/questions/${questionId}`, payload)); }
export async function removeQuestion(quizId, questionId) { await api.delete(`${base}/quizzes/${quizId}/questions/${questionId}`); }
export async function reorderQuestions(quizId, ids) { await api.post(`${base}/quizzes/${quizId}/questions/reorder`, { ids }); }

export async function listCourseAssignments(courseId) { return unwrapCollection(await api.get(`${base}/courses/${courseId}/assignments`)); }
export async function getAssignment(courseId, assignmentId) { return unwrapResource(await api.get(assignmentUrl(courseId, assignmentId))); }
export async function createAssignment(courseId, payload) { return unwrapResource(await api.post(`${base}/courses/${courseId}/assignments`, payload)); }
export async function updateAssignment(courseId, assignmentId, payload) { return unwrapResource(await api.patch(assignmentUrl(courseId, assignmentId), payload)); }
export async function archiveAssignment(courseId, assignmentId) { return unwrapResource(await api.delete(assignmentUrl(courseId, assignmentId))); }
export async function publishAssignment(assignmentId) { return unwrapResource(await api.post(`${base}/assignments/${assignmentId}/publication`)); }
export async function unpublishAssignment(assignmentId) { return unwrapResource(await api.delete(`${base}/assignments/${assignmentId}/publication`)); }
export async function uploadAssignmentAttachment(assignmentId, file) {
    const form = new FormData();
    form.append('file', file);
    return unwrapResource(await api.post(`${base}/assignments/${assignmentId}/attachments`, form));
}
export async function removeAssignmentAttachment(assignmentId, attachmentId) { await api.delete(`${base}/assignments/${assignmentId}/attachments/${attachmentId}`); }
export async function downloadAssignmentAttachment(attachment) { await downloadBlob(`${base}/assignment-attachments/${attachment.id}/download`, attachment.original_filename); }
