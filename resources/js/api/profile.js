import { api } from './client';
import { unwrapResource } from './responses';

export async function fetchProfile() {
    return unwrapResource(await api.get('api/v1/me/profile'));
}

export async function updateProfile(payload) {
    return unwrapResource(await api.patch('api/v1/me/profile', payload));
}

export async function uploadAvatar(file) {
    const form = new FormData();
    form.append('avatar', file);
    return unwrapResource(await api.post('api/v1/me/avatar', form));
}

export async function removeAvatar() {
    return unwrapResource(await api.delete('api/v1/me/avatar'));
}

export async function changePassword(payload) {
    return (await api.put('api/v1/me/password', payload)).data;
}
