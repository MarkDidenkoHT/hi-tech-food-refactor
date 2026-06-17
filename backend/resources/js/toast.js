import { escapeHtml } from './dom';

let container = null;

function getContainer() {
    if (container && document.body.contains(container)) {
        return container;
    }

    container = document.createElement('div');
    container.className = 'pointer-events-none fixed inset-x-0 top-3 z-50 flex flex-col items-center gap-2 px-4';
    document.body.appendChild(container);

    return container;
}

/**
 * Show a transient toast notification.
 *
 * @param {string} message
 * @param {{type?: 'success'|'error'}} [options]
 */
export function showToast(message, options = {}) {
    const { type = 'success' } = options;

    const colors = type === 'error'
        ? 'bg-destructive text-white'
        : 'bg-text text-bg';

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto max-w-sm rounded-xl px-4 py-2 text-sm font-medium shadow-lg ${colors}`;
    toast.innerHTML = escapeHtml(message);

    getContainer().appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3000);
}
