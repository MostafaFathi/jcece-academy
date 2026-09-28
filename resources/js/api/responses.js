export function unwrapResource(response) {
    return response.data.data;
}

export function unwrapCollection(response) {
    return {
        items: response.data.data ?? [],
        links: response.data.links ?? {},
        meta: response.data.meta ?? null,
    };
}
