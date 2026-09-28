export const orderStatuses = ['pending', 'awaiting_payment', 'paid', 'completed', 'cancelled', 'refunded'];
export const paymentStatuses = ['pending_review', 'paid', 'rejected'];
export const paymentMethods = ['bank_transfer', 'wallet', 'manual'];

export function moneyText(amount, locale = 'en') {
    if (!/^\d+\.\d{2}$/.test(String(amount))) return '—';
    const [integer, fraction] = String(amount).split('.');
    const whole = new Intl.NumberFormat(locale === 'ar' ? 'ar' : 'en').format(BigInt(integer));
    return locale === 'ar' ? `${whole}٫${fraction.replace(/\d/g, (digit) => '٠١٢٣٤٥٦٧٨٩'[digit])}` : `${whole}.${fraction}`;
}

export function commerceError(error, t) {
    if (error?.code === 'network') return t('errors.network');
    if (error?.status === 401) return t('commerce.sessionExpired');
    if (error?.status === 403) return t('errors.forbiddenText');
    if (error?.status === 404) return t('commerce.notFound');
    if (error?.status === 429) return t('commerce.rateLimited');
    if (error?.status >= 500) return t('errors.server');
    return error?.message || t('errors.generic');
}

export function canSubmitPayment(order) {
    return ['pending', 'awaiting_payment'].includes(order?.status)
        && Array.isArray(order?.payments)
        && !order.payments.some((payment) => ['pending_review', 'paid'].includes(payment.status));
}

export function accessState(order) {
    if (order?.status === 'completed') return 'completed';
    if (order?.status === 'paid') return 'paid';
    if (['cancelled', 'refunded'].includes(order?.status)) return order.status;
    if (order?.payments?.some((payment) => payment.status === 'pending_review')) return 'pending_review';
    return 'pending';
}
