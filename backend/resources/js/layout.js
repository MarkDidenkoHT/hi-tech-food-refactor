import { escapeHtml } from './dom';
import { getSelectedRestaurantId } from './restaurant-context';
import { MENU_ITEMS } from './menu-items';

const ROLE_LABELS = {
    admin: 'Админ',
    director: 'Директор',
    manager: 'Менеджер',
    staff: 'Сотрудник',
};

/**
 * Render the app shell (header + content placeholder) for the given route.
 *
 * @param {object} ctx
 * @param {{title: string}} route
 * @param {string} path
 * @returns {string}
 */
export function renderShell(ctx, route, path) {
    const { user } = ctx;
    const isHome = path === '';
    const restaurants = user.restaurants ?? [];
    const restaurantNames = restaurants.map((r) => r.name).join(', ');
    const selectedRestaurantId = getSelectedRestaurantId(restaurants);

    const menuItems = MENU_ITEMS.filter((item) => item.roles.includes(user.role));

    return `
        <div class="flex min-h-screen flex-col">
            <header class="sticky top-0 z-10 flex items-center gap-3 border-b border-separator bg-surface px-4 py-3">
                <button id="nav-menu" type="button" aria-label="Меню"
                    class="-ml-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-lg text-link">
                    &#9776;
                </button>
                <div id="nav-menu-dropdown" class="hidden absolute left-2 top-14 z-20 w-60 rounded-2xl bg-surface p-2 shadow-lg ring-1 ring-separator">
                    ${menuItems.map((item) => `
                        <a href="#/${item.path}"
                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium hover:bg-bg">
                            <span class="text-lg">${item.icon}</span>
                            <span>${escapeHtml(item.label)}</span>
                        </a>
                    `).join('')}
                </div>
                ${!isHome ? `
                    <button id="nav-back" type="button" aria-label="Назад"
                        class="-ml-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-lg text-link">
                        &larr;
                    </button>
                ` : ''}
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-semibold">${escapeHtml(route.title)}</h1>
                    ${isHome ? `
                        <p class="truncate text-xs text-hint">
                            ${escapeHtml(`${user.first_name} ${user.last_name ?? ''}`.trim())}
                            &middot; ${escapeHtml(ROLE_LABELS[user.role] ?? user.role)}
                            ${restaurants.length === 1 ? `&middot; ${escapeHtml(restaurantNames)}` : ''}
                        </p>
                    ` : ''}
                </div>
                ${restaurants.length > 1 ? `
                    <select id="restaurant-select" aria-label="Ресторан"
                        class="shrink-0 max-w-[40%] rounded-xl border border-separator bg-bg px-2 py-1.5 text-xs">
                        ${restaurants.map((restaurant) => `
                            <option value="${restaurant.id}" ${restaurant.id === selectedRestaurantId ? 'selected' : ''}>
                                ${escapeHtml(restaurant.name)}
                            </option>
                        `).join('')}
                    </select>
                ` : ''}
                ${isHome ? `
                    <button id="nav-logout" type="button"
                        class="shrink-0 rounded-xl px-3 py-1.5 text-sm font-medium text-destructive">
                        Выйти
                    </button>
                ` : ''}
            </header>
            <main id="view" class="flex-1 pb-8"></main>
        </div>
    `;
}

/**
 * The path to navigate to when the back control is used.
 *
 * @param {string} path
 * @returns {string}
 */
export function parentPath(path) {
    const segments = path.split('/');
    segments.pop();

    return segments.join('/');
}
