import { api } from './client';
import { unwrapCollection } from './responses';

export async function fetchInstructorOptions(params = {}) { return unwrapCollection(await api.get('/api/v1/admin/instructor-options', { params })); }
