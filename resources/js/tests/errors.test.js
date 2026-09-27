import { describe, expect, it } from 'vitest';
import { ApiError, normalizeApiError } from '../api/errors';

describe('normalizeApiError', () => {
    it('normalizes validation responses with field errors', () => {
        const result = normalizeApiError({ response: { status: 422, data: { message: 'Invalid.', errors: { email: ['Required.'] } } } });

        expect(result).toBeInstanceOf(ApiError);
        expect(result.code).toBe('validation');
        expect(result.errors.email).toEqual(['Required.']);
    });

    it.each([[401, 'unauthenticated'], [403, 'forbidden'], [404, 'not_found'], [500, 'server']])('normalizes status %s', (status, code) => {
        expect(normalizeApiError({ response: { status, data: {} } }).code).toBe(code);
    });

    it('identifies network failures', () => {
        expect(normalizeApiError(new Error('offline')).code).toBe('network');
    });
});
