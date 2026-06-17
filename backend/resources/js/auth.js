import { apiFetch, setToken } from './api';
import { getInitData } from './telegram';

/**
 * Authenticate with the backend using the Telegram WebApp initData.
 * Stores the issued API token and returns the authenticated user.
 *
 * @returns {Promise<object>}
 */
export async function login() {
    const data = await apiFetch('/auth/telegram', {
        method: 'POST',
        body: { init_data: getInitData() },
        auth: false,
    });

    setToken(data.token);

    return data.user;
}

/**
 * Revoke the current API token, ignoring network errors.
 */
export async function logout() {
    try {
        await apiFetch('/auth/logout', { method: 'POST' });
    } catch {
        // Token may already be invalid; clear it locally regardless.
    } finally {
        setToken(null);
    }
}
