export class ApiError extends Error {
    constructor({ status = null, message, errors = {}, code = 'unknown', originalError = null }) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
        this.code = code;
        this.originalError = originalError;
    }
}

const statusCodes = {
    401: 'unauthenticated',
    403: 'forbidden',
    404: 'not_found',
    419: 'csrf_expired',
    422: 'validation',
    429: 'rate_limited',
};

export function normalizeApiError(error) {
    if (error instanceof ApiError) {
        return error;
    }

    if (!error?.response) {
        return new ApiError({
            message: 'تعذر الاتصال بالخادم. تحقق من اتصالك وحاول مجددًا.',
            code: 'network',
            originalError: error,
        });
    }

    const status = error.response.status;
    const data = error.response.data ?? {};

    return new ApiError({
        status,
        message: data.message || (status >= 500 ? 'حدث خطأ في الخادم.' : 'تعذر إتمام الطلب.'),
        errors: data.errors ?? {},
        code: statusCodes[status] ?? (status >= 500 ? 'server' : 'request'),
        originalError: error,
    });
}
