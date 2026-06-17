import { escapeHtml } from '../dom';

export function render(container, ctx, route) {
    container.innerHTML = `
        <div class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
            <span class="text-4xl">🚧</span>
            <p class="text-base font-semibold">${escapeHtml(route.title)}</p>
            <p class="text-sm text-hint">Этот раздел скоро появится.</p>
        </div>
    `;
}
