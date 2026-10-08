import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import i18n, { setLocale } from '../i18n';
import { useAuthStore } from '../stores/auth';
import { navigationByArea, visibleNavigation } from '../composables/navigation';
import { routes } from '../router';
import * as reports from '../api/reports';
import ReportsPage from '../pages/ReportsPage.vue';

vi.mock('../api/reports', () => ({ fetchReport: vi.fn(), exportReport: vi.fn() }));

function render(scope = 'admin') {
    return mount(ReportsPage, { props: { scope }, global: { plugins: [createPinia(), i18n], stubs: { RouterLink: { template: '<a><slot /></a>' } } } });
}

describe('Phase 15G reporting workspace', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        setActivePinia(createPinia());
        setLocale('en');
        reports.fetchReport.mockResolvedValue({ data: { summary: [{ currency: 'JOD', gross_sales: '100.00', refunds: '10.00', net_sales: '90.00' }], rows: { data: [{ id: 1, order_number: 'JCEC-1', currency: 'JOD', total: '100.00' }], current_page: 1, last_page: 1 } } });
    });

    it('gates Admin navigation and shows server-owned sales summary', async () => {
        const router = createRouter({ history: createMemoryHistory(), routes });
        expect(router.resolve('/admin/reports').meta.permissions).toEqual(['reports.view']);
        expect(visibleNavigation(navigationByArea.admin, { can: (permission) => permission === 'courses.view', canAny: () => true, hasAnyRole: () => false }).some((item) => item.route === 'admin.reports')).toBe(false);
        const wrapper = render();
        useAuthStore().setUser({ id: 1, roles: ['admin'], permissions: ['reports.view', 'reports.export'] });
        await flushPromises();
        expect(wrapper.text()).toContain('90.00');
        expect(wrapper.text()).toContain('JCEC-1');
        expect(wrapper.findAll('button').some((button) => button.text() === 'csv')).toBe(true);
        expect(wrapper.get('table thead').classes()).toContain('bg-[#faf8f8]');
        expect(wrapper.get('table thead').classes()).not.toContain('bg-brand');
    });

    it('applies date filters and changes reports without calculating totals in Vue', async () => {
        const wrapper = render();
        await flushPromises();
        const dates = wrapper.findAll('input[type="date"]');
        await dates[0].setValue('2026-09-01');
        await dates[1].setValue('2026-09-30');
        await wrapper.get('form').trigger('submit');
        expect(reports.fetchReport).toHaveBeenLastCalledWith('admin', 'sales', expect.objectContaining({ from: '2026-09-01', to: '2026-09-30' }));
        await wrapper.findAll('nav button').find((button) => button.text() === 'Courses').trigger('click');
        expect(reports.fetchReport).toHaveBeenLastCalledWith('admin', 'courses', expect.any(Object));
    });

    it('keeps Instructor in an own-scope workspace and supports Arabic direction', async () => {
        setLocale('ar');
        const wrapper = render('instructor');
        await flushPromises();
        expect(wrapper.attributes('dir')).toBe('rtl');
        expect(wrapper.text()).toContain('الدورات');
        expect(wrapper.text()).not.toContain('المبيعات');
        expect(wrapper.findAll('button').some((button) => button.text() === 'csv')).toBe(false);
        expect(reports.fetchReport).toHaveBeenCalledWith('instructor', 'courses', expect.any(Object));
    });

    it('downloads only when export permission exists and preserves applied filters', async () => {
        const wrapper = render();
        useAuthStore().setUser({ id: 1, roles: ['admin'], permissions: ['reports.view', 'reports.export'] });
        await flushPromises();
        await wrapper.findAll('input[type="date"]')[0].setValue('2026-10-01');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'csv').trigger('click');
        await flushPromises();
        expect(reports.exportReport).toHaveBeenCalledWith('sales', 'csv', expect.objectContaining({ from: '2026-10-01' }));
    });

    it('paginates server rows and presents empty and error states', async () => {
        reports.fetchReport.mockResolvedValueOnce({ data: { summary: [], rows: { data: [{ id: 1, order_number: 'FIRST' }], current_page: 1, last_page: 2 } } })
            .mockResolvedValueOnce({ data: { summary: [], rows: { data: [], current_page: 2, last_page: 2 } } })
            .mockRejectedValueOnce(new Error('offline'));
        const wrapper = render();
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Next').trigger('click');
        await flushPromises();
        expect(reports.fetchReport).toHaveBeenLastCalledWith('admin', 'sales', expect.objectContaining({ page: 2 }));
        expect(wrapper.text()).toContain('No data');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Unable to load report');
    });
});
