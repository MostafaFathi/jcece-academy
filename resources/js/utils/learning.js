export function safeLearningUrl(value) {
    if (typeof value !== 'string' || !/^https?:\/\//i.test(value)) return null;
    try {
        const url = new URL(value);
        let decoded = value;
        for (let iteration = 0; iteration < 5; iteration++) decoded = decodeURIComponent(decoded);
        if (url.username || url.password || /[%\\\u0000-\u0020\u007f]|storage|private|lesson-resources|file_path|storage_path|storage_disk|x-amz-|x-goog-|signature=/i.test(decoded)) return null;
        return url.href;
    } catch { return null; }
}

export function learningErrorKey(error) {
    if (error?.status === 401) return 'learning.unauthenticated';
    if (error?.status === 403) return 'learning.forbidden';
    if (error?.status === 404) return 'learning.notFound';
    if (error?.context === 'download') return 'learning.downloadFailed';
    if (error?.status === 422) return 'learning.invalidProgress';
    if (error?.status === 429) return 'learning.rateLimited';
    return 'learning.requestFailed';
}

export const blocksLearning = (error) => [401, 403, 404].includes(error?.status);
