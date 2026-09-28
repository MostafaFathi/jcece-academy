export function publicMediaUrl(value) {
    if (!value || typeof value !== 'string') {
        return null;
    }

    if (/^https?:\/\//i.test(value) || value.startsWith('/')) {
        return value;
    }

    if (/^[a-z][a-z\d+.-]*:/i.test(value)) {
        return null;
    }

    return `/${value.replace(/^\/+/, '')}`;
}

export function formatAmount(value, locale = 'ar') {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return '—';
    }

    return new Intl.NumberFormat(locale === 'ar' ? 'ar' : 'en', {
        minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

export function formatDate(value, locale = 'ar') {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar' : 'en', { dateStyle: 'medium' }).format(date);
}
