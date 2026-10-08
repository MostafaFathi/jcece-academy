import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import LessonVideoUploader from '../components/admin/LessonVideoUploader.vue';
import * as video from '../api/bunny-video';

vi.mock('../api/bunny-video', () => ({
    createVideoUpload: vi.fn(),
    getVideoUpload: vi.fn(),
    getVideoUploadOverview: vi.fn(),
    previewVideo: vi.fn(),
    removeVideoUpload: vi.fn(),
    uploadBunnyVideo: vi.fn(),
}));

function deferred() {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });
    return { promise, resolve };
}

async function chooseFile(wrapper) {
    const input = wrapper.get('input[type="file"]');
    Object.defineProperty(input.element, 'files', { configurable: true, value: [new File(['data'], 'lesson.mp4', { type: 'video/mp4' })] });
    await input.trigger('change');
}

beforeEach(() => {
    vi.resetAllMocks();
    setLocale('en');
    video.getVideoUploadOverview.mockResolvedValue({ upload: null, settings: { configured: true, max_upload_megabytes: 100, cleanup_pending: [] } });
    video.getVideoUpload.mockResolvedValue({ id: 1, status: 'ready' });
});

afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('protected lesson video uploader', () => {
    it('explains that upload is complete while video processing continues', async () => {
        setLocale('ar');
        video.getVideoUploadOverview.mockResolvedValue({
            upload: { id: 3, status: 'processing', encode_progress: 5, filename: 'asghar-ali.mp4' },
            settings: { configured: true, max_upload_megabytes: 100, cleanup_pending: [] },
        });
        const wrapper = mount(LessonVideoUploader, { props: { lesson: { id: 7 } }, global: { plugins: [i18n] } });
        await flushPromises();
        expect(wrapper.text()).toContain('اكتمل رفع الملف');
        expect(wrapper.text()).toContain('لا حاجة إلى إعادة رفعه');
        expect(wrapper.get('bdi[dir="ltr"]').text()).toBe('asghar-ali.mp4');
        expect(wrapper.find('input[type="file"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('رفع الفيديو');
        expect(wrapper.get('progress').attributes('value')).toBe('5');
        wrapper.unmount();
    });

    it('shows the publishing next step and hides upload controls when video is ready', async () => {
        setLocale('ar');
        video.getVideoUploadOverview.mockResolvedValue({
            upload: { id: 3, status: 'ready', encode_progress: 100, filename: 'asghar-ali.mp4' },
            settings: { configured: true, max_upload_megabytes: 100, cleanup_pending: [] },
        });
        const wrapper = mount(LessonVideoUploader, { props: { lesson: { id: 7 } }, global: { plugins: [i18n] } });
        await flushPromises();
        expect(wrapper.text()).toContain('الفيديو جاهز');
        expect(wrapper.text()).toContain('حفظ التغييرات');
        expect(wrapper.find('input[type="file"]').exists()).toBe(false);
        expect(wrapper.findAll('button').some((button) => button.text() === 'رفع الفيديو')).toBe(false);
        expect(wrapper.findAll('button').some((button) => button.text() === 'معاينة الفيديو المحمي')).toBe(true);
        wrapper.unmount();
    });

    it('automatically uploads a file selected while creating the lesson', async () => {
        const file = new File(['video data'], 'new-lesson.mp4', { type: 'video/mp4' });
        video.createVideoUpload.mockResolvedValue({ id: 2, status: 'uploading', upload: {} });
        video.uploadBunnyVideo.mockResolvedValue('https://video.bunnycdn.com/tusupload/session');
        const wrapper = mount(LessonVideoUploader, { props: { lesson: { id: 8 }, initialFile: file }, global: { plugins: [i18n] } });
        await flushPromises();
        expect(video.createVideoUpload).toHaveBeenCalledWith(8, expect.objectContaining({ filename: 'new-lesson.mp4', size_bytes: file.size }));
        expect(video.uploadBunnyVideo).toHaveBeenCalledWith(file, {}, expect.any(Object));
        wrapper.unmount();
    });

    it('shows the preparation stage instead of a misleading zero-percent transfer', async () => {
        const pending = deferred();
        video.createVideoUpload.mockReturnValue(pending.promise);
        const wrapper = mount(LessonVideoUploader, { props: { lesson: { id: 7 } }, global: { plugins: [i18n] } });
        await flushPromises();
        await chooseFile(wrapper);
        await wrapper.get('button').trigger('click');
        expect(wrapper.text()).toContain('Creating a secure upload session');
        expect(wrapper.find('progress').exists()).toBe(false);
        pending.resolve({ id: 1, status: 'uploading', upload: {} });
        await flushPromises();
        wrapper.unmount();
    });

    it('continues uploading when browser session storage is unavailable', async () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('Storage unavailable'); });
        video.createVideoUpload.mockResolvedValue({ id: 1, status: 'uploading', upload: {} });
        video.uploadBunnyVideo.mockResolvedValue('https://video.bunnycdn.com/tusupload/session');
        const wrapper = mount(LessonVideoUploader, { props: { lesson: { id: 7 } }, global: { plugins: [i18n] } });
        await flushPromises();
        await chooseFile(wrapper);
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(video.uploadBunnyVideo).toHaveBeenCalledOnce();
        expect(wrapper.text()).not.toContain('Storage unavailable');
        wrapper.unmount();
    });

    it('starts an upload when randomUUID is unavailable in the browser', async () => {
        vi.stubGlobal('crypto', {
            getRandomValues: (bytes) => {
                for (let index = 0; index < bytes.length; index++) bytes[index] = index;
                return bytes;
            },
        });
        video.createVideoUpload.mockResolvedValue({ id: 1, status: 'uploading', upload: {} });
        video.uploadBunnyVideo.mockResolvedValue('https://video.bunnycdn.com/tusupload/session');
        const wrapper = mount(LessonVideoUploader, { props: { lesson: { id: 7 } }, global: { plugins: [i18n] } });
        await flushPromises();
        await chooseFile(wrapper);
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(video.createVideoUpload).toHaveBeenCalledWith(7, expect.objectContaining({
            request_id: '00010203-0405-4607-8809-0a0b0c0d0e0f',
        }));
        expect(wrapper.text()).not.toContain('randomUUID');
        wrapper.unmount();
    });
});
