import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api, downloadBlob } from '../api/client';
import * as reviews from '../api/reviews';
import * as support from '../api/support';
import { hasAttachmentErrors, ticketFormData, validateAttachments } from '../utils/support';

vi.mock('../api/client', () => ({ api: { get: vi.fn(), post: vi.fn(), patch: vi.fn() }, downloadBlob: vi.fn() }));
beforeEach(() => { vi.resetAllMocks(); api.get.mockResolvedValue({ data: { data: [{ id: 1 }], meta: { current_page: 1 } } }); api.post.mockResolvedValue({ data: { data: { id: 2 } } }); api.patch.mockResolvedValue({ data: { data: { id: 2, status: 'pending' } } }); });

describe('review contract and public boundary', () => {
    it('uses student-specific create, own detail and update routes', async () => {
        await reviews.fetchMyReview('safety course');
        await reviews.createReview('safety course', { rating: 4, title: null, body: null });
        await reviews.updateReview(2, { rating: 5 });
        expect(api.get).toHaveBeenCalledWith('/api/v1/me/courses/safety%20course/review');
        expect(api.post).toHaveBeenCalledWith('/api/v1/me/courses/safety%20course/reviews', { rating: 4, title: null, body: null });
        expect(api.patch).toHaveBeenCalledWith('/api/v1/me/reviews/2', { rating: 5 });
    });
    it('keeps public reviews on the published-only backend route', async () => {
        expect((await reviews.fetchCourseReviews('safety', { page: 2 })).items).toEqual([{ id: 1 }]);
        expect(api.get).toHaveBeenCalledWith('api/v1/courses/safety/reviews', { params: { page: 2 } });
    });
});
describe('support contract and protected attachments', () => {
    it('uses owner-scoped list/detail/message/reopen endpoints', async () => {
        await support.fetchTickets(2); await support.fetchTicket(7); await support.fetchTicketMessages(7, 3); await support.reopenTicket(7);
        expect(api.get).toHaveBeenCalledWith('/api/v1/me/support-tickets', { params: { page: 2 } });
        expect(api.get).toHaveBeenCalledWith('/api/v1/me/support-tickets/7');
        expect(api.get).toHaveBeenCalledWith('/api/v1/me/support-tickets/7/messages', { params: { page: 3 } });
        expect(api.post).toHaveBeenCalledWith('/api/v1/me/support-tickets/7/reopen');
    });
    it('sends multipart public messages only and downloads by protected ID', async () => {
        const file = new File(['safe'], 'safe.txt', { type: 'text/plain' });
        const form = ticketFormData({ subject: 'Help', category: 'general', body: 'Question', related_order_id: '' }, [file]);
        await support.createTicket(form); await support.replyToTicket(7, ticketFormData({ body: 'Reply' }, [])); await support.downloadTicketAttachment(9);
        expect(Array.from(form.keys())).toEqual(['subject', 'category', 'body', 'attachments[]']);
        expect(form.get('attachments[]')).toBe(file);
        expect(api.post).toHaveBeenCalledWith('/api/v1/me/support-tickets', form);
        expect(api.post.mock.calls[1][0]).toBe('/api/v1/me/support-tickets/7/messages');
        expect(downloadBlob).toHaveBeenCalledWith('/api/v1/me/support-ticket-attachments/9/download', 'support-attachment-9');
    });
    it('enforces declared frontend file limits without passing storage metadata', () => {
        expect(validateAttachments(Array.from({ length: 6 }, () => new File(['x'], 'file.txt')))).toBe('tooManyFiles');
        expect(validateAttachments([new File(['x'], 'bad.exe')])).toBe('invalidFile');
        expect(validateAttachments([new File(['x'], 'safe.PDF')])).toBeNull();
        expect(Array.from(ticketFormData({ body: 'Safe' }).keys())).toEqual(['body']);
        expect(hasAttachmentErrors({ 'attachments.0': ['Invalid file'] })).toBe(true);
    });
});
