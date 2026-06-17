/**
 * Get the Telegram WebApp object, if running inside Telegram.
 *
 * @returns {object|null}
 */
export function getTelegram() {
    return window.Telegram?.WebApp ?? null;
}

/**
 * Initialize the Telegram WebApp: signal readiness, expand to full height,
 * and sync the theme colors into CSS custom properties.
 *
 * @returns {object|null}
 */
export function initTelegram() {
    const tg = getTelegram();

    if (!tg) {
        return null;
    }

    tg.ready();
    tg.expand();

    applyTheme(tg);
    tg.onEvent('themeChanged', () => applyTheme(tg));

    return tg;
}

function applyTheme(tg) {
    const root = document.documentElement;

    for (const [key, value] of Object.entries(tg.themeParams ?? {})) {
        root.style.setProperty(`--tg-theme-${key.replace(/_/g, '-')}`, value);
    }
}

/**
 * The raw initData string used to authenticate with the backend.
 *
 * @returns {string}
 */
export function getInitData() {
    return getTelegram()?.initData ?? '';
}

/**
 * Show a yes/no confirmation dialog, using Telegram's native prompt when available.
 *
 * @param {string} message
 * @returns {Promise<boolean>}
 */
export function confirmDialog(message) {
    const tg = getTelegram();

    if (tg?.showConfirm) {
        return new Promise((resolve) => tg.showConfirm(message, (ok) => resolve(ok)));
    }

    return Promise.resolve(window.confirm(message));
}
