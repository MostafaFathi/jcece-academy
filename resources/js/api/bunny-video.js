import { api } from './client';

const chunkSize = 2 * 1024 * 1024;

function withTimeout(signal, milliseconds) {
    const timeout = AbortSignal.timeout(milliseconds);
    return signal ? AbortSignal.any([signal, timeout]) : timeout;
}

export function videoUploadPath(lessonId) {
    return `/api/v1/admin/lessons/${encodeURIComponent(lessonId)}/video-uploads`;
}

function validTusUrl(value) {
    const url = new URL(value);
    if (url.protocol !== 'https:' || url.hostname !== 'video.bunnycdn.com' || url.username || url.password || url.port) throw new Error('Unsafe upload location');
    return url.href;
}

function metadata(value) {
    const bytes = new TextEncoder().encode(value);
    return btoa(Array.from(bytes, (byte) => String.fromCharCode(byte)).join(''));
}

function tusHeaders(authorization) {
    return {
        'Tus-Resumable': '1.0.0',
        AuthorizationSignature: authorization.authorization_signature,
        AuthorizationExpire: String(authorization.authorization_expire),
        LibraryId: authorization.library_id,
        VideoId: authorization.video_id,
    };
}

async function offsetAt(url, authorization, signal) {
    const response = await fetch(url, { method: 'HEAD', headers: tusHeaders(authorization), signal: withTimeout(signal, 20000), credentials: 'omit' });
    if (response.status === 404 || response.status === 410) return null;
    if (!response.ok) throw new Error('Unable to resume video upload');
    const offset = Number(response.headers.get('Upload-Offset'));
    if (!Number.isSafeInteger(offset) || offset < 0) throw new Error('Invalid upload offset');
    return offset;
}

export async function uploadBunnyVideo(file, authorization, { resumeUrl = null, onLocation = () => {}, onProgress = () => {}, signal } = {}) {
    const endpoint = validTusUrl(authorization.endpoint);
    let location = resumeUrl ? validTusUrl(resumeUrl) : null;
    let offset = location ? await offsetAt(location, authorization, signal) : null;

    if (offset === null) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                ...tusHeaders(authorization),
                'Upload-Length': String(file.size),
                'Upload-Metadata': `filetype ${metadata(file.type)},title ${metadata(file.name)}`,
            },
            signal: withTimeout(signal, 20000),
            credentials: 'omit',
        });
        if (response.status !== 201) throw new Error('Unable to start video upload');
        const returnedLocation = response.headers.get('Location');
        if (!returnedLocation) throw new Error('Upload location unavailable');
        location = validTusUrl(new URL(returnedLocation, endpoint).href);
        onLocation(location);
        offset = 0;
    }

    if (offset > file.size) throw new Error('Upload offset exceeds file size');
    onProgress(Math.round(offset / file.size * 100));

    while (offset < file.size) {
        let succeeded = false;
        for (let attempt = 0; attempt < 4 && !succeeded; attempt++) {
            if (signal?.aborted) throw new DOMException('Upload canceled', 'AbortError');
            try {
                const response = await fetch(location, {
                    method: 'PATCH',
                    headers: { ...tusHeaders(authorization), 'Content-Type': 'application/offset+octet-stream', 'Upload-Offset': String(offset) },
                    body: file.slice(offset, Math.min(offset + chunkSize, file.size)),
                    signal: withTimeout(signal, 120000),
                    credentials: 'omit',
                });
                if (response.status === 204) {
                    const next = Number(response.headers.get('Upload-Offset'));
                    if (!Number.isSafeInteger(next) || next <= offset || next > file.size) throw new Error('Invalid upload offset');
                    offset = next;
                    onProgress(Math.round(offset / file.size * 100));
                    succeeded = true;
                    continue;
                }
                if (![409, 423, 429, 500, 502, 503, 504].includes(response.status)) throw new Error('Video upload rejected');
            } catch (error) {
                if (signal?.aborted || error.message === 'Video upload rejected') throw error;
            }
            await new Promise((resolve) => setTimeout(resolve, 400 * (attempt + 1)));
            const recovered = await offsetAt(location, authorization, signal);
            if (recovered === null) throw new Error('Upload session expired; retry with the same file');
            offset = recovered;
            onProgress(Math.round(offset / file.size * 100));
            if (offset === file.size) succeeded = true;
        }
        if (!succeeded) throw new Error('Video upload did not complete; retry');
    }

    return location;
}

export async function createVideoUpload(lessonId, payload) {
    const response = await api.post(videoUploadPath(lessonId), payload, { timeout: 20000 });
    return response.data.data;
}

export async function getVideoUpload(lessonId, uploadId = null) {
    const response = await api.get(`${videoUploadPath(lessonId)}${uploadId ? `/${uploadId}` : ''}`, { timeout: 20000 });
    return response.data.data;
}

export async function getVideoUploadOverview(lessonId) {
    const response = await api.get(videoUploadPath(lessonId), { timeout: 20000 });
    return { upload: response.data.data, settings: response.data.meta };
}

export async function removeVideoUpload(lessonId, uploadId) {
    const response = await api.delete(`${videoUploadPath(lessonId)}/${uploadId}`);
    return response.data.data;
}

export async function previewVideo(lessonId) {
    const response = await api.post(`/api/v1/admin/lessons/${encodeURIComponent(lessonId)}/video-preview`);
    return response.data.data;
}
