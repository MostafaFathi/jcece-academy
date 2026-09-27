import { describe, expect, it } from 'vitest';
import i18n, { applyDocumentLocale, messages, setLocale } from '../i18n';

describe('localization', () => {
    it('ships Arabic and English strings', () => {
        expect(messages.ar.common.login).toBeTruthy();
        expect(messages.en.common.login).toBeTruthy();
    });

    it('updates language, direction, and preference', () => {
        applyDocumentLocale('ar');
        expect(document.documentElement.dir).toBe('rtl');

        setLocale('en');
        expect(document.documentElement.lang).toBe('en');
        expect(document.documentElement.dir).toBe('ltr');
        expect(localStorage.getItem('jcec.locale')).toBe('en');
        expect(i18n.global.locale.value).toBe('en');
    });
});
