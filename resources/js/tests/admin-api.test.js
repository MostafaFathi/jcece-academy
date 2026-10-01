import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../api/client';
import * as admin from '../api/admin';
import * as users from '../api/admin-users';
import * as instructors from '../api/admin-instructors';
import * as options from '../api/instructor-options';

vi.mock('../api/client', () => ({ api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() } }));

describe('admin endpoint adapters', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        api.get.mockResolvedValue({ data: { data: [{ id: 2 }], meta: { total: 1 } } });
        api.post.mockResolvedValue({ data: { data: { id: 2 } } });
        api.patch.mockResolvedValue({ data: { data: { id: 2 } } });
        api.delete.mockResolvedValue({});
    });
    it('uses only the existing category CRUD routes and pagination', async () => {
        expect(await admin.fetchAdminCategories(2)).toEqual({ items: [{ id: 2 }], links: {}, meta: { total: 1 } });
        expect(api.get).toHaveBeenCalledWith('/api/v1/admin/categories', { params: { page: 2 } });
        api.get.mockResolvedValueOnce({ data: { data: { id: 2 } } });
        expect(await admin.fetchAdminCategory(2)).toEqual({ id: 2 });
        expect(api.get).toHaveBeenLastCalledWith('/api/v1/admin/categories/2');
        await admin.createAdminCategory({ name: 'Safety' });
        await admin.updateAdminCategory(2, { name: 'Health' });
        await admin.deleteAdminCategory(2);
        expect(api.post).toHaveBeenCalledWith('/api/v1/admin/categories', { name: 'Safety' });
        expect(api.patch).toHaveBeenCalledWith('/api/v1/admin/categories/2', { name: 'Health' });
        expect(api.delete).toHaveBeenCalledWith('/api/v1/admin/categories/2');
    });
    it('uses server-filtered paginated course routes and preserves payload decimals', async () => {
        await admin.fetchAdminCourses({ status: 'draft', page: 3 });
        expect(api.get).toHaveBeenCalledWith('/api/v1/admin/courses', { params: { status: 'draft', page: 3 } });
        api.get.mockResolvedValueOnce({ data: { data: { id: 7 } } });
        expect(await admin.fetchAdminCourse(7)).toEqual({ id: 7 });
        const payload = { price: '123.45', access_duration_days: null };
        await admin.updateAdminCourse(7, payload);
        expect(api.patch).toHaveBeenCalledWith('/api/v1/admin/courses/7', payload);
        await admin.deleteAdminCourse(7);
        expect(api.delete).toHaveBeenCalledWith('/api/v1/admin/courses/7');
    });
    it('keeps account, instructor and selector calls in focused API modules', async () => {
        await users.fetchAdminUsers({ role: 'student', page: 2 });
        expect(api.get).toHaveBeenCalledWith('/api/v1/admin/users', { params: { role: 'student', page: 2 } });
        await instructors.fetchAdminInstructors({ search: 'Teacher' });
        expect(api.get).toHaveBeenCalledWith('/api/v1/admin/instructors', { params: { search: 'Teacher' } });
        await options.fetchInstructorOptions({ search: 'Teacher', page: 3 });
        expect(api.get).toHaveBeenCalledWith('/api/v1/admin/instructor-options', { params: { search: 'Teacher', page: 3 } });
        await users.createAdminUser({ name: 'New' }); await instructors.createAdminInstructor({ name: 'Trainer' });
        expect(api.post).toHaveBeenCalledWith('/api/v1/admin/users', { name: 'New' });
        expect(api.post).toHaveBeenCalledWith('/api/v1/admin/instructors', { name: 'Trainer' });
        await users.updateAdminUser(3, { name: 'Updated' }); await instructors.updateAdminInstructor(4, { job_title: 'Teacher' });
        expect(api.patch).toHaveBeenCalledWith('/api/v1/admin/users/3', { name: 'Updated' });
        expect(api.patch).toHaveBeenCalledWith('/api/v1/admin/instructors/4', { job_title: 'Teacher' });
    });
});
