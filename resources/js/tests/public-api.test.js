import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../api/client';
import { fetchCategories } from '../api/categories';
import { fetchCourse, fetchCourses } from '../api/courses';
import { fetchPackage, fetchPackages } from '../api/packages';
import { fetchCourseReviews } from '../api/reviews';

vi.mock('../api/client', () => ({ api: { get: vi.fn() } }));

describe('public catalog API modules', () => {
    beforeEach(() => api.get.mockReset());

    it('maps the category collection', async () => {
        api.get.mockResolvedValue({ data: { data: [{ id: 1, name: 'Engineering' }] } });
        await expect(fetchCategories()).resolves.toEqual([{ id: 1, name: 'Engineering' }]);
        expect(api.get).toHaveBeenCalledWith('api/v1/categories');
    });

    it('preserves course filters and paginator metadata', async () => {
        const params = { search: 'BIM', category: 'engineering', level: 'beginner', sort: 'price_asc', page: 2 };
        api.get.mockResolvedValue({ data: { data: [{ id: 9 }], meta: { current_page: 2, last_page: 3 }, links: {} } });
        await expect(fetchCourses(params)).resolves.toEqual({ items: [{ id: 9 }], meta: { current_page: 2, last_page: 3 }, links: {} });
        expect(api.get).toHaveBeenCalledWith('api/v1/courses', { params });
    });

    it('loads course details and only the public reviews endpoint', async () => {
        api.get.mockResolvedValueOnce({ data: { data: { slug: 'safe/course' } } }).mockResolvedValueOnce({ data: { data: [{ rating: 5 }], meta: { current_page: 1 } } });
        await fetchCourse('safe/course');
        await fetchCourseReviews('safe/course', { page: 1 });
        expect(api.get).toHaveBeenNthCalledWith(1, 'api/v1/courses/safe%2Fcourse');
        expect(api.get).toHaveBeenNthCalledWith(2, 'api/v1/courses/safe%2Fcourse/reviews', { params: { page: 1 } });
    });

    it('maps package listing and detail responses', async () => {
        api.get.mockResolvedValueOnce({ data: { data: [{ slug: 'path' }], meta: { current_page: 1 } } }).mockResolvedValueOnce({ data: { data: { slug: 'path', courses: [] } } });
        await expect(fetchPackages({ type: 'learning_path' })).resolves.toMatchObject({ items: [{ slug: 'path' }] });
        await expect(fetchPackage('path')).resolves.toMatchObject({ slug: 'path' });
        expect(api.get).toHaveBeenNthCalledWith(1, 'api/v1/packages', { params: { type: 'learning_path' } });
        expect(api.get).toHaveBeenNthCalledWith(2, 'api/v1/packages/path');
    });
});
