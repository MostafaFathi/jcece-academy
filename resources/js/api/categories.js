import { api } from './client';
import { unwrapCollection, unwrapResource } from './responses';

export async function fetchCategories() {
    return unwrapCollection(await api.get('api/v1/categories')).items;
}

export async function fetchCategory(id) {
    return unwrapResource(await api.get(`api/v1/categories/${id}`));
}
