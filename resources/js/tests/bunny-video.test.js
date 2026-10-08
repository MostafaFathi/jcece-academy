import { afterEach, describe, expect, it, vi } from 'vitest';
import { uploadBunnyVideo } from '../api/bunny-video';

const authorization = { endpoint: 'https://video.bunnycdn.com/tusupload', library_id: '773691', video_id: '123e4567-e89b-42d3-a456-426614174000', authorization_signature: 'short-lived-signature', authorization_expire: 1800000000 };

afterEach(() => { vi.unstubAllGlobals(); });

describe('Bunny resumable upload', () => {
    it('sends video bytes directly to the documented TUS endpoint with upload-scoped authorization', async () => {
        const sent = [];
        vi.stubGlobal('fetch', vi.fn(async (url, options) => {
            sent.push({ url, options });
            if (options.method === 'POST') return { status: 201, headers: new Headers({ Location: '/tusupload/session-1' }) };
            return { status: 204, headers: new Headers({ 'Upload-Offset': '4' }) };
        }));
        const progress = [];
        await uploadBunnyVideo(new File(['data'], 'lesson.mp4', { type: 'video/mp4' }), authorization, { onProgress: (value) => progress.push(value) });
        expect(sent.map(({ options }) => options.method)).toEqual(['POST', 'PATCH']);
        expect(sent[0].url).toBe('https://video.bunnycdn.com/tusupload');
        expect(sent[0].options.headers.AuthorizationSignature).toBe('short-lived-signature');
        expect(sent[1].options.headers['Upload-Offset']).toBe('0');
        expect(progress.at(-1)).toBe(100);
        expect(JSON.stringify(sent)).not.toContain('library-api-key');
    });

    it('rejects a provider location outside Bunny before sending a chunk', async () => {
        const fetcher = vi.fn(async () => ({ status: 201, headers: new Headers({ Location: 'https://evil.test/tusupload' }) }));
        vi.stubGlobal('fetch', fetcher);
        await expect(uploadBunnyVideo(new File(['data'], 'lesson.mp4', { type: 'video/mp4' }), authorization)).rejects.toThrow('Unsafe upload location');
        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it('resumes from the server offset instead of uploading earlier bytes again', async () => {
        const methods = [];
        vi.stubGlobal('fetch', vi.fn(async (_url, options) => {
            methods.push(options.method);
            if (options.method === 'HEAD') return { ok: true, status: 200, headers: new Headers({ 'Upload-Offset': '2' }) };
            return { status: 204, headers: new Headers({ 'Upload-Offset': '4' }) };
        }));
        const progress = [];
        await uploadBunnyVideo(new File(['data'], 'lesson.mp4', { type: 'video/mp4' }), authorization, { resumeUrl: 'https://video.bunnycdn.com/tusupload/session-1', onProgress: (value) => progress.push(value) });
        expect(methods).toEqual(['HEAD', 'PATCH']);
        expect(progress).toEqual([50, 100]);
    });
});
