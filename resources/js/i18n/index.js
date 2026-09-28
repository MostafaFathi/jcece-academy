import { createI18n } from 'vue-i18n';
import ar from './ar';
import en from './en';

export const messages = { ar, en };

const savedLocale = localStorage.getItem('jcec.locale');
const locale = savedLocale === 'en' ? 'en' : 'ar';

export function applyDocumentLocale(selectedLocale) {
    document.documentElement.lang = selectedLocale;
    document.documentElement.dir = selectedLocale === 'ar' ? 'rtl' : 'ltr';
}

applyDocumentLocale(locale);

const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'ar', messages });

export function setLocale(selectedLocale) {
    const normalizedLocale = selectedLocale === 'en' ? 'en' : 'ar';
    i18n.global.locale.value = normalizedLocale;
    localStorage.setItem('jcec.locale', normalizedLocale);
    applyDocumentLocale(normalizedLocale);
}

export default i18n;
