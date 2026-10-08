import { afterEach, describe, expect, it, vi } from 'vitest';
import { createRequestId } from '../utils/request-id';

afterEach(() => { vi.unstubAllGlobals(); });

describe('secure request IDs', () => {
    it('uses secure random bytes when randomUUID is unavailable', () => {
        vi.stubGlobal('crypto', {
            getRandomValues: (bytes) => {
                for (let index = 0; index < bytes.length; index++) bytes[index] = index;
                return bytes;
            },
        });

        expect(createRequestId()).toBe('00010203-0405-4607-8809-0a0b0c0d0e0f');
    });

    it('does not use predictable randomness if Web Crypto is unavailable', () => {
        vi.stubGlobal('crypto', undefined);

        expect(() => createRequestId()).toThrow('Secure random generation is unavailable');
    });
});
