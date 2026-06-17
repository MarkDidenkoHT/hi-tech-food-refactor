const TOKEN_KEY = 'restaurant_app_token';

export function getToken() {
    return sessionStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    if (token) {
        sessionStorage.setItem(TOKEN_KEY, token);
    } else {
        sessionStorage.removeItem(TOKEN_KEY);
    }
}

export class ApiError extends Error {
    constructor(message, status, errors) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors ?? null;
    }
}

/**
 * Call the JSON API under /api.
 *
 * @param {string} path Path beginning with "/", e.g. "/me".
 * @param {{method?: string, body?: unknown, auth?: boolean}} [options]
 */
export async function apiFetch(path, options = {}) {
    const { method = 'GET', body, auth = true } = options;

    const headers = {
        Accept: 'application/json',
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    if (auth) {
        const token = getToken();

        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
    }

    const response = await fetch(`/api${path}`, {
        method,
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    const text = await response.text();
    let data = null;

    if (text) {
        try {
            data = JSON.parse(text);
        } catch {
            data = null;
        }
    }

    if (!response.ok) {
        throw new ApiError(data?.message ?? `Ошибка запроса (${response.status})`, response.status, data?.errors);
    }

    return data;
}
