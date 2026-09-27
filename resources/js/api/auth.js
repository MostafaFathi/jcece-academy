import { api } from './client';

export async function initializeCsrf() {
    await api.get('sanctum/csrf-cookie');
}

export async function fetchAuthenticatedUser() {
    const response = await api.get('api/v1/auth/user');

    return response.data.data;
}

export async function login(credentials) {
    await initializeCsrf();
    await api.post('api/v1/auth/login', credentials);

    return fetchAuthenticatedUser();
}

export async function logout() {
    await api.post('api/v1/auth/logout');
}
