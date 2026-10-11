export function catalogMediaPayload(payload, uploadKey, update = false) {
    if (!payload[uploadKey]) return payload;

    const formData = new FormData();
    for (const [key, value] of Object.entries(payload)) {
        if (Array.isArray(value)) {
            if (value.length === 0) formData.append(`clear_${key}`, '1');
            value.forEach((item, index) => formData.append(`${key}[${index}]`, item));
        } else if (value !== undefined) {
            formData.append(key, value === null ? '' : typeof value === 'boolean' ? (value ? '1' : '0') : value);
        }
    }
    if (update) formData.append('_method', 'PATCH');
    return formData;
}
