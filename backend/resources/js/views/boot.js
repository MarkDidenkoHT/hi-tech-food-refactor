import { escapeHtml } from '../dom';

export function renderLoading() {
    return `
        <div class="flex min-h-screen items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-link border-t-transparent"></div>
        </div>
    `;
}

export function renderNotRegistered(message) {
    return `
        <div class="flex min-h-screen flex-col items-center justify-center gap-3 px-6 text-center">
            <span class="text-4xl">🔒</span>
            <p class="text-base font-semibold">Вы не зарегистрированы</p>
            <p class="text-sm text-hint">${escapeHtml(message)}</p>
        </div>
    `;
}

export function renderError(message) {
    return `
        <div class="flex min-h-screen flex-col items-center justify-center gap-3 px-6 text-center">
            <span class="text-4xl">⚠️</span>
            <p class="text-base font-semibold">Что-то пошло не так</p>
            <p class="text-sm text-hint">${escapeHtml(message)}</p>
        </div>
    `;
}
