import { api, downloadBlob } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const base = '/api/v1/instructor';
const course = (courseId) => `${base}/courses/${courseId}`;
const assignment = (courseId, assignmentId) => `${course(courseId)}/assignments/${assignmentId}`;
const quiz = (courseId, quizId) => `${course(courseId)}/quizzes/${quizId}`;

export async function fetchInstructorSummary() { return unwrapResource(await api.get(`${base}/dashboard-summary`)); }
export async function fetchInstructorCourses(params = {}) { return unwrapCollection(await api.get(`${base}/courses`, { params })); }
export async function fetchInstructorCourse(courseId) { return unwrapResource(await api.get(course(courseId))); }
export async function updateInstructorCourse(courseId, payload) { return unwrapResource(await api.patch(`/api/v1/admin/courses/${courseId}`, payload)); }
export async function fetchInstructorCurriculum(courseId) { return unwrapCollection(await api.get(`${course(courseId)}/curriculum`)); }
export async function fetchInstructorQuizzes(courseId) { return unwrapCollection(await api.get(`${course(courseId)}/quizzes`)); }
export async function fetchInstructorQuiz(courseId, quizId) { return unwrapResource(await api.get(quiz(courseId, quizId))); }
export async function fetchInstructorQuizAttempts(courseId, quizId, page = 1) { return unwrapCollection(await api.get(`${quiz(courseId, quizId)}/attempts`, { params: { page } })); }
export async function fetchInstructorQuizAttempt(courseId, quizId, attemptId) { return unwrapResource(await api.get(`${quiz(courseId, quizId)}/attempts/${attemptId}`)); }
export async function fetchInstructorAssignments(courseId) { return unwrapCollection(await api.get(`${course(courseId)}/assignments`)); }
export async function fetchInstructorAssignment(courseId, assignmentId) { return unwrapResource(await api.get(assignment(courseId, assignmentId))); }
export async function fetchInstructorSubmissions(courseId, assignmentId, params = {}) { return unwrapCollection(await api.get(`${assignment(courseId, assignmentId)}/submissions`, { params })); }
export async function fetchInstructorSubmission(courseId, assignmentId, submissionId) { return unwrapResource(await api.get(`${assignment(courseId, assignmentId)}/submissions/${submissionId}`)); }
export async function gradeInstructorSubmission(submissionId, payload) { return unwrapResource(await api.post(`/api/v1/admin/assignment-submissions/${submissionId}/grade`, payload)); }
export async function requestInstructorRevision(submissionId, payload) { return unwrapResource(await api.post(`/api/v1/admin/assignment-submissions/${submissionId}/revision`, payload)); }
export async function correctInstructorGrade(submissionId, payload) { return unwrapResource(await api.post(`/api/v1/admin/assignment-submissions/${submissionId}/grade-corrections`, payload)); }
export async function downloadInstructorSubmissionFile(fileId, filename) { return downloadBlob(`/api/v1/admin/assignment-submission-files/${fileId}/download`, filename); }
export async function downloadInstructorAssignmentAttachment(attachmentId, filename) { return downloadBlob(`/api/v1/admin/assignment-attachments/${attachmentId}/download`, filename); }
