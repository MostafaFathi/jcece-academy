export function isSupportedVideoFile(file) {
    if (!file?.size) return false;

    const mimeTypes = { mp4: 'video/mp4', mov: 'video/quicktime', webm: 'video/webm' };
    const extension = file.name.split('.').pop()?.toLowerCase();

    return mimeTypes[extension] === file.type;
}
