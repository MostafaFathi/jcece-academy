import { api, downloadBlob } from './client';
import { unwrapCollection, unwrapResource } from './responses';

const me = '/api/v1/me';
export const fetchQuizzes = async () => (await api.get(`${me}/quizzes`)).data.data;
export const fetchQuiz = async (id) => unwrapResource(await api.get(`${me}/quizzes/${id}`));
export const fetchQuizAttempts = async (id) => (await api.get(`${me}/quizzes/${id}/attempts`)).data.data;
export const startQuizAttempt = async (id) => unwrapResource(await api.post(`${me}/quizzes/${id}/attempts`));
export const fetchQuizAttempt = async (id) => unwrapResource(await api.get(`${me}/quiz-attempts/${id}`));
export const fetchQuizResult = async (id) => unwrapResource(await api.get(`${me}/quiz-attempts/${id}/result`));
export const saveQuizAnswers = async (id, answers) => unwrapResource(await api.patch(`${me}/quiz-attempts/${id}/answers`, { answers }));
export const submitQuizAttempt = async (id) => unwrapResource(await api.post(`${me}/quiz-attempts/${id}/submit`));

export const fetchAssignments = async () => (await api.get(`${me}/assignments`)).data.data;
export const fetchAssignment = async (id) => unwrapResource(await api.get(`${me}/assignments/${id}`));
export const fetchAssignmentSubmissions = async (id) => (await api.get(`${me}/assignments/${id}/submissions`)).data.data;
export const startAssignmentDraft = async (id) => unwrapResource(await api.post(`${me}/assignments/${id}/submissions`));
export const fetchAssignmentSubmission = async (id) => unwrapResource(await api.get(`${me}/assignment-submissions/${id}`));
export const saveAssignmentDraft = async (id, textAnswer) => unwrapResource(await api.patch(`${me}/assignment-submissions/${id}`, { text_answer: textAnswer }));
export async function uploadAssignmentFiles(id, files) {
    const form = new FormData();
    for (const file of files) form.append('files[]', file);
    return unwrapResource(await api.post(`${me}/assignment-submissions/${id}/files`, form));
}
export const deleteAssignmentFile = async (id, fileId) => unwrapResource(await api.delete(`${me}/assignment-submissions/${id}/files/${fileId}`));
export const submitAssignment = async (id) => unwrapResource(await api.post(`${me}/assignment-submissions/${id}/submit`));
export const downloadAssignmentAttachment = (id) => downloadBlob(`${me}/assignment-attachments/${id}/download`, `assignment-attachment-${id}`);
export const downloadSubmissionFile = (id) => downloadBlob(`${me}/assignment-submission-files/${id}/download`, `submission-file-${id}`);

export const fetchCertificates = async (page = 1) => unwrapCollection(await api.get(`${me}/certificates`, { params: { page } }));
export const fetchCertificate = async (id) => unwrapResource(await api.get(`${me}/certificates/${id}`));
export const fetchCertificateEligibility = async (slug) => unwrapResource(await api.get(`${me}/courses/${encodeURIComponent(slug)}/certificate-eligibility`));
export const issueCertificate = async (slug) => unwrapResource(await api.post(`${me}/courses/${encodeURIComponent(slug)}/certificates`));
export const downloadCertificate = (id) => downloadBlob(`${me}/certificates/${id}/download`, `certificate-${id}.pdf`);
export const verifyCertificate = async (token) => unwrapResource(await api.get(`/api/v1/certificates/verify/${encodeURIComponent(token)}`));
