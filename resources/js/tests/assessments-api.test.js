import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api, downloadBlob } from '../api/client';
import * as assessments from '../api/assessments';

vi.mock('../api/client', () => ({ api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }, downloadBlob: vi.fn() }));
beforeEach(() => { vi.resetAllMocks(); for (const method of ['get', 'post', 'patch', 'delete']) api[method].mockResolvedValue({ data: { data: { id: 7 } } }); });

describe('assessment API contract', () => {
    it('uses only existing quiz routes and saves exact option sets', async () => {
        await assessments.fetchQuizzes(); await assessments.fetchQuiz(3); await assessments.fetchQuizAttempts(3);
        await assessments.startQuizAttempt(3); await assessments.fetchQuizAttempt(7); await assessments.fetchQuizResult(7);
        await assessments.saveQuizAnswers(7, [{ question_id: 1, option_ids: [4, 5] }]); await assessments.submitQuizAttempt(7);
        expect(api.get.mock.calls.map(([url]) => url)).toEqual(['/api/v1/me/quizzes', '/api/v1/me/quizzes/3', '/api/v1/me/quizzes/3/attempts', '/api/v1/me/quiz-attempts/7', '/api/v1/me/quiz-attempts/7/result']);
        expect(api.patch).toHaveBeenCalledWith('/api/v1/me/quiz-attempts/7/answers', { answers: [{ question_id: 1, option_ids: [4, 5] }] });
        expect(api.post.mock.calls.map(([url]) => url)).toEqual(['/api/v1/me/quizzes/3/attempts', '/api/v1/me/quiz-attempts/7/submit']);
    });
    it('uses real assignment draft, file and finalization routes', async () => {
        await assessments.fetchAssignments(); await assessments.fetchAssignment(3); await assessments.fetchAssignmentSubmissions(3);
        await assessments.startAssignmentDraft(3); await assessments.fetchAssignmentSubmission(7); await assessments.saveAssignmentDraft(7, 'Answer');
        const file = new File(['test'], 'answer.txt', { type: 'text/plain' });
        await assessments.uploadAssignmentFiles(7, [file]); await assessments.deleteAssignmentFile(7, 9); await assessments.submitAssignment(7);
        expect(api.patch).toHaveBeenCalledWith('/api/v1/me/assignment-submissions/7', { text_answer: 'Answer' });
        expect(api.post.mock.calls[1][1]).toBeInstanceOf(FormData);
        expect(api.post.mock.calls[1][1].getAll('files[]')).toEqual([file]);
        expect(api.delete).toHaveBeenCalledWith('/api/v1/me/assignment-submissions/7/files/9');
        expect(api.post.mock.calls.at(-1)[0]).toBe('/api/v1/me/assignment-submissions/7/submit');
        await assessments.downloadAssignmentAttachment(2); await assessments.downloadSubmissionFile(9);
        expect(downloadBlob.mock.calls.map(([url]) => url)).toEqual(['/api/v1/me/assignment-attachments/2/download', '/api/v1/me/assignment-submission-files/9/download']);
    });
    it('keeps certificates private except public verification', async () => {
        await assessments.fetchCertificates(2); await assessments.fetchCertificateEligibility('bim / one'); await assessments.issueCertificate('bim / one');
        await assessments.downloadCertificate(7); await assessments.verifyCertificate('a/b');
        expect(api.get).toHaveBeenCalledWith('/api/v1/me/certificates', { params: { page: 2 } });
        expect(api.get).toHaveBeenCalledWith('/api/v1/me/courses/bim%20%2F%20one/certificate-eligibility');
        expect(api.post).toHaveBeenCalledWith('/api/v1/me/courses/bim%20%2F%20one/certificates');
        expect(downloadBlob).toHaveBeenCalledWith('/api/v1/me/certificates/7/download', 'certificate-7.pdf');
        expect(api.get).toHaveBeenCalledWith('/api/v1/certificates/verify/a%2Fb');
    });
});
