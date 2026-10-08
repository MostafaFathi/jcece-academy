import axios from 'axios';
import { normalizeApiError } from './errors';

export const api = axios.create({
    baseURL: '/',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    withXSRFToken: true,
});

let unauthorizedHandler = null;

export function installUnauthorizedHandler(handler) {
    unauthorizedHandler = handler;
}

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const normalizedError = normalizeApiError(error);

        if (normalizedError.status === 419 && error.config && !error.config.__csrfRetried && !String(error.config.url).includes('sanctum/csrf-cookie')) {
            error.config.__csrfRetried = true;
            await api.get('sanctum/csrf-cookie');

            return api.request(error.config);
        }

        if (normalizedError.status === 401 && unauthorizedHandler) {
            unauthorizedHandler(normalizedError);
        }

        return Promise.reject(normalizedError);
    },
);

api.interceptors.request.use((config) => {
    config.headers['X-Locale'] = localStorage.getItem('jcec.locale') === 'en' ? 'en' : 'ar';
    return config;
});

export async function downloadBlob(url, fallbackFilename = 'download') {
    const response = await api.get(url, { responseType: 'blob' });
    const disposition = response.headers['content-disposition'] ?? '';
    const encodedFilename = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    const plainFilename = disposition.match(/filename="?([^";]+)"?/i)?.[1];
    const filename = encodedFilename ? decodeURIComponent(encodedFilename) : (plainFilename || fallbackFilename);
    const objectUrl = URL.createObjectURL(response.data);
    const link = document.createElement('a');

    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
}
