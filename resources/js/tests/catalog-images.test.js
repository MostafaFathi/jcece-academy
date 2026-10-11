import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import i18n from '../i18n';
import CategoryCard from '../components/public/CategoryCard.vue';
import CatalogImageField from '../components/admin/CatalogImageField.vue';
import { catalogMediaPayload } from '../api/catalog-media';

const RouterLinkStub = { props: ['to'], template: '<a><slot /></a>' };

describe('catalog images', () => {
    it('uses the category image as the whole homepage card while retaining an accessible name', () => {
        const wrapper = mount(CategoryCard, {
            props: { compact: true, category: { name: 'الهندسة', slug: 'engineering', image: '/storage/catalog/categories/photo.png' } },
            global: { stubs: { RouterLink: RouterLinkStub } },
        });

        expect(wrapper.find('img[src="/storage/catalog/categories/photo.png"]').exists()).toBe(true);
        expect(wrapper.find('h3').exists()).toBe(false);
        expect(wrapper.find('a').attributes('aria-label')).toBe('الهندسة');
    });

    it('keeps a readable category card when no image has been chosen', () => {
        const wrapper = mount(CategoryCard, {
            props: { compact: true, category: { name: 'الهندسة', slug: 'engineering', image: null } },
            global: { stubs: { RouterLink: RouterLinkStub } },
        });

        expect(wrapper.find('h3').text()).toBe('الهندسة');
    });

    it('previews a selected image and allows clearing it', async () => {
        const objectUrl = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:preview');
        const revokeUrl = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
        const wrapper = mount(CatalogImageField, {
            props: { id: 'catalog-image', label: 'Image', source: null, file: null },
            global: { plugins: [i18n] },
        });
        const file = new File(['image'], 'image.png', { type: 'image/png' });

        await wrapper.setProps({ file });
        expect(wrapper.find('img[src="blob:preview"]').exists()).toBe(true);
        await wrapper.find('button').trigger('click');
        expect(wrapper.emitted('clear')).toHaveLength(1);
        wrapper.unmount();
        expect(objectUrl).toHaveBeenCalledWith(file);
        expect(revokeUrl).toHaveBeenCalledWith('blob:preview');
        objectUrl.mockRestore();
        revokeUrl.mockRestore();
    });

    it('sends image updates as multipart with method override and preserves empty list intent', () => {
        const file = new File(['image'], 'image.png', { type: 'image/png' });
        const body = catalogMediaPayload({ title: 'Course', is_featured: false, learning_outcomes: [], thumbnail_upload: file }, 'thumbnail_upload', true);

        expect(body).toBeInstanceOf(FormData);
        expect(body.get('_method')).toBe('PATCH');
        expect(body.get('thumbnail_upload')).toBe(file);
        expect(body.get('is_featured')).toBe('0');
        expect(body.get('clear_learning_outcomes')).toBe('1');
    });
});
