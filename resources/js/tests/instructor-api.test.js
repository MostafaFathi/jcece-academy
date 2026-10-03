import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api, downloadBlob } from '../api/client';
import * as instructor from '../api/instructor';

vi.mock('../api/client', () => ({ api: { get: vi.fn(), post: vi.fn(), patch: vi.fn() }, downloadBlob: vi.fn() }));
beforeEach(() => {
    vi.resetAllMocks();
    api.get.mockResolvedValue({ data: { data: [{ id: 1 }], meta: { total: 1 } } });
    api.post.mockResolvedValue({ data: { data: { id: 1 } } });
    api.patch.mockResolvedValue({ data: { data: { id: 1 } } });
});

describe('instructor API boundaries', () => {
    it('reads only scoped instructor course and quiz URLs', async () => {
        await instructor.fetchInstructorCourses({ search: 'own', page: 2 });
        await instructor.fetchInstructorCurriculum(4);
        await instructor.fetchInstructorQuizAttempts(4, 5, 3);
        await instructor.fetchInstructorQuizAttempt(4, 5, 6);
        expect(api.get).toHaveBeenNthCalledWith(1, '/api/v1/instructor/courses', { params: { search: 'own', page: 2 } });
        expect(api.get).toHaveBeenNthCalledWith(2, '/api/v1/instructor/courses/4/curriculum');
        expect(api.get).toHaveBeenNthCalledWith(3, '/api/v1/instructor/courses/4/quizzes/5/attempts', { params: { page: 3 } });
        expect(api.get).toHaveBeenNthCalledWith(4, '/api/v1/instructor/courses/4/quizzes/5/attempts/6');
    });
    it('uses paginated filtered submissions and dedicated mutation endpoints', async () => {
        await instructor.fetchInstructorSubmissions(4, 7, { status: 'submitted', page: 2 });
        await instructor.gradeInstructorSubmission(11, { score: '75.50' });
        await instructor.requestInstructorRevision(11, { feedback: 'Revise' });
        await instructor.correctInstructorGrade(11, { score: '80.00', reason: 'Rubric' });
        expect(api.get).toHaveBeenCalledWith('/api/v1/instructor/courses/4/assignments/7/submissions', { params: { status: 'submitted', page: 2 } });
        expect(api.post).toHaveBeenNthCalledWith(1, '/api/v1/admin/assignment-submissions/11/grade', { score: '75.50' });
        expect(api.post).toHaveBeenNthCalledWith(2, '/api/v1/admin/assignment-submissions/11/revision', { feedback: 'Revise' });
        expect(api.post).toHaveBeenNthCalledWith(3, '/api/v1/admin/assignment-submissions/11/grade-corrections', { score: '80.00', reason: 'Rubric' });
    });
    it('downloads through authenticated blob helper without storage paths', async () => {
        await instructor.downloadInstructorSubmissionFile(9, 'work.pdf');
        await instructor.downloadInstructorAssignmentAttachment(8, 'brief.pdf');
        expect(downloadBlob).toHaveBeenNthCalledWith(1, '/api/v1/admin/assignment-submission-files/9/download', 'work.pdf');
        expect(downloadBlob).toHaveBeenNthCalledWith(2, '/api/v1/admin/assignment-attachments/8/download', 'brief.pdf');
    });
});
