export function csrfToken() {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export async function apiFetch(url, { json, headers, ...rest } = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...rest,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(json !== undefined ? { 'Content-Type': 'application/json' } : {}),
            ...(headers ?? {}),
        },
        ...(json !== undefined ? { body: JSON.stringify(json) } : {}),
    });

    const data =
        response.status === 204
            ? null
            : await response.json().catch(() => null);

    if (!response.ok) {
        const error = new Error(data?.message ?? 'Ocurrió un error inesperado.');
        error.status = response.status;
        error.data = data;
        throw error;
    }

    return data;
}
