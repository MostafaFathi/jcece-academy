import { describe, expect, it } from 'vitest';
import { formatAmount, publicMediaUrl } from '../utils/catalog';

describe('catalog presentation helpers', () => {
    it('allows web and relative image paths without inventing storage paths', () => {
        expect(publicMediaUrl('https://cdn.test/course.jpg')).toBe('https://cdn.test/course.jpg');
        expect(publicMediaUrl('/images/course.jpg')).toBe('/images/course.jpg');
        expect(publicMediaUrl('images/course.jpg')).toBe('/images/course.jpg');
    });

    it('rejects unsafe media schemes', () => {
        expect(publicMediaUrl('javascript:alert(1)')).toBeNull();
        expect(publicMediaUrl('data:text/html,test')).toBeNull();
    });

    it('formats numeric API price strings without inventing a currency', () => {
        expect(formatAmount('125.50', 'en')).toBe('125.50');
        expect(formatAmount('invalid', 'en')).toBe('—');
    });
});
