export const ticketCategories = ['general', 'technical', 'course_content', 'payment', 'enrollment', 'certificate', 'other'];
export const ticketStatuses = ['open', 'in_progress', 'waiting_for_student', 'resolved', 'closed'];
export const ticketPriorities = ['low', 'normal', 'high', 'urgent'];
export const attachmentExtensions = '.pdf,.jpg,.jpeg,.png,.webp,.txt,.doc,.docx';
const allowedExtensions = new Set(attachmentExtensions.split(',').map((extension) => extension.slice(1)));
export const maximumAttachmentCount = 5;
export const maximumAttachmentBytes = 10 * 1024 * 1024;

export function validateAttachments(files) {
    if (files.length > maximumAttachmentCount) return 'tooManyFiles';
    if (files.some((file) => file.size > maximumAttachmentBytes || !allowedExtensions.has(file.name.split('.').pop()?.toLowerCase()))) return 'invalidFile';
    return null;
}

export function hasAttachmentErrors(errors) {
    return Object.keys(errors ?? {}).some((key) => key === 'attachments' || key.startsWith('attachments.'));
}

export function ticketFormData(fields, files = []) {
    const form = new FormData();
    Object.entries(fields).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') form.append(key, String(value));
    });
    files.forEach((file) => form.append('attachments[]', file));
    return form;
}
