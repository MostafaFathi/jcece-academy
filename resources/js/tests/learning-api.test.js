import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api, downloadBlob } from '../api/client';
import * as learning from '../api/learning';
vi.mock('../api/client', () => ({ api: { get: vi.fn(), post: vi.fn(), patch: vi.fn() }, downloadBlob: vi.fn() }));
beforeEach(() => { vi.resetAllMocks(); });
describe('learning API contract', () => {
    it('fetches owned paginated courses', async () => {
        api.get.mockResolvedValue({ data: { data: [{ id: 1 }], meta: { current_page: 2 } } });
        expect(await learning.fetchMyCourses(2)).toEqual({ items: [{ id: 1 }], links: {}, meta: { current_page: 2 } });
        expect(api.get).toHaveBeenCalledWith('/api/v1/me/courses', { params: { page: 2 } });
    });
    it.each([['fetchLearningCourse', 'learn'], ['fetchCourseProgress', 'progress']])('uses the real slug contract for %s', async (method, suffix) => {
        api.get.mockResolvedValue({ data: { data: { has_access: true } } });
        expect(await learning[method]('bim / one')).toEqual({ has_access: true });
        expect(api.get).toHaveBeenCalledWith(`/api/v1/me/courses/bim%20%2F%20one/${suffix}`);
    });
    it('uses POST completion and PATCH video progress, with no invented undo endpoint', async () => {
        api.post.mockResolvedValue({ data: { data: { status: 'completed' } } });
        api.patch.mockResolvedValue({ data: { data: { last_position_seconds: 17 } } });
        await learning.completeLesson('bim', 3);
        await learning.saveLessonProgress('bim', 3, { last_position_seconds: 17 });
        expect(api.post).toHaveBeenCalledWith('/api/v1/me/courses/bim/lessons/3/complete');
        expect(api.patch).toHaveBeenCalledWith('/api/v1/me/courses/bim/lessons/3/progress', { last_position_seconds: 17 });
    });
    it('downloads through the authenticated blob helper with nested IDs only', async () => {
        await learning.downloadLessonResource('bim', 3, 8);
        expect(downloadBlob).toHaveBeenCalledWith('/api/v1/me/courses/bim/lessons/3/resources/8/download', 'lesson-resource-8');
    });
});
