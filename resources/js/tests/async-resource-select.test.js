import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import AsyncResourceSelect from '../components/ui/AsyncResourceSelect.vue';

function render(props) { return mount(AsyncResourceSelect, { props, global: { plugins: [i18n] } }); }

describe('searchable resource selector', () => {
    it('finds a named record, paginates, and emits only its ID', async () => {
        setLocale('en');
        const loader = vi.fn().mockResolvedValueOnce({ items: [{ id: 7, title: 'Safety' }], meta: { current_page: 1, last_page: 2 } }).mockResolvedValueOnce({ items: [{ id: 8, title: 'Planning' }], meta: { current_page: 2, last_page: 2 } });
        const wrapper = render({ id: 'course', modelValue: '', label: 'Course', loader });
        await flushPromises();
        expect(wrapper.text()).toContain('Safety');
        await wrapper.findAll('button').find((button) => button.text() === 'Load more options').trigger('click');
        await flushPromises();
        await wrapper.get('#course').setValue('8');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([8]);
        wrapper.unmount();
    });

    it('hydrates selected labels and supports multiple choices in Arabic', async () => {
        setLocale('ar');
        const loader = vi.fn().mockResolvedValue({ items: [{ id: 3, title: 'دورة التخطيط' }], meta: { current_page: 1, last_page: 1 } });
        const resolver = vi.fn().mockResolvedValue({ id: 9, title: 'دورة السلامة' });
        const wrapper = render({ id: 'courses', modelValue: [9], label: 'الدورات', loader, resolver, multiple: true });
        await flushPromises();
        expect(wrapper.text()).toContain('دورة السلامة');
        await wrapper.get('#courses').setValue('3');
        await wrapper.findAll('button').find((button) => button.text() === 'تطبيق').trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([[9, 3]]);
        wrapper.unmount();
    });
});
