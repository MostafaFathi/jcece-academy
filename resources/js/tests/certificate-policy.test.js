import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import CourseCertificateRequirements from '../components/admin/CourseCertificateRequirements.vue';
import CertificateApprovals from '../components/admin/CertificateApprovals.vue';
import * as policy from '../api/certificate-policy';

vi.mock('../api/certificate-policy', () => ({
    fetchCourseCertificateRequirements: vi.fn(), saveCourseCertificateRequirements: vi.fn(),
    fetchCertificateApprovalRequests: vi.fn(), approveCertificateRequest: vi.fn(), requestCertificateApproval: vi.fn(),
}));

function render(component, props = {}) { return mount(component, { props, global: { plugins: [i18n] } }); }

beforeEach(() => {
    vi.resetAllMocks();
    setLocale('en');
    policy.fetchCourseCertificateRequirements.mockResolvedValue({
        certificate_enabled: true, required_lesson_percentage: '80.00', final_exam_required: false,
        final_exam_quiz_id: null, final_exam_passing_percentage: null, required_assignment_ids: [],
        admin_approval_required: false, requirements_version: 1,
        quizzes: [{ id: 4, title: 'Final quiz', status: 'published' }], assignments: [{ id: 8, title: 'Project', status: 'published' }],
    });
    policy.saveCourseCertificateRequirements.mockResolvedValue({ requirements_version: 2 });
    policy.fetchCertificateApprovalRequests.mockResolvedValue({ data: [{ id: 10, status: 'pending', course: { title: 'Safety' }, user: { name: 'Student', email: 'student@example.test' } }] });
    policy.approveCertificateRequest.mockResolvedValue({ id: 10, status: 'approved' });
});

describe('course certificate settings', () => {
    it('shows authorized configuration controls, selectors and sends explicit IDs', async () => {
        const wrapper = render(CourseCertificateRequirements, { courseId: 3 });
        await flushPromises();
        expect(wrapper.text()).toContain('Certificate requirements');
        await wrapper.find('input[type="checkbox"]').setValue(true);
        await wrapper.findAll('input[type="checkbox"]')[1].setValue(true);
        await wrapper.find('#certificate-quiz').setValue('4');
        await wrapper.find('#certificate-score').setValue('70');
        await wrapper.findAll('input[type="checkbox"]')[2].setValue(true);
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();
        expect(policy.saveCourseCertificateRequirements).toHaveBeenCalledWith(3, expect.objectContaining({
            final_exam_required: true, final_exam_quiz_id: 4, final_exam_passing_percentage: 70, required_assignment_ids: [8],
        }));
        wrapper.unmount();
    });

    it('renders server validation errors and Arabic labels', async () => {
        setLocale('ar');
        policy.saveCourseCertificateRequirements.mockRejectedValue({ status: 422, errors: { required_lesson_percentage: ['Invalid percentage'] } });
        const wrapper = render(CourseCertificateRequirements, { courseId: 3 });
        await flushPromises();
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();
        expect(wrapper.text()).toContain('متطلبات الشهادة');
        expect(wrapper.text()).toContain('Invalid percentage');
        wrapper.unmount();
    });
});

describe('certificate approvals', () => {
    it('shows a pending request and approves through the separate workflow', async () => {
        const wrapper = render(CertificateApprovals);
        await flushPromises();
        expect(wrapper.text()).toContain('Student');
        await wrapper.find('button').trigger('click');
        await flushPromises();
        expect(policy.approveCertificateRequest).toHaveBeenCalledWith(10);
        wrapper.unmount();
    });
});
