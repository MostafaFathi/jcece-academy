import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

export async function fetchPackages(params = {}) {
    return unwrapCollection(await api.get('api/v1/packages', { params }));
}

export async function fetchPackage(slug) {
    return unwrapResource(await api.get(`api/v1/packages/${encodeURIComponent(slug)}`));
}
