/**
 * Escape a value for safe interpolation into an HTML template string.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[char]));
}

/**
 * A spinner with an accompanying message, used for inline loading states.
 *
 * @param {string} [message]
 * @returns {string}
 */
export function loadingHtml(message = 'Загрузка…') {
    return `
        <div class="flex items-center justify-center gap-2 p-4 text-sm text-hint">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-link border-t-transparent"></span>
            <span>${escapeHtml(message)}</span>
        </div>
    `;
}
