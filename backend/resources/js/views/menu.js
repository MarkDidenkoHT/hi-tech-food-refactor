import { apiFetch } from '../api';
import { escapeHtml, loadingHtml } from '../dom';
import { getSelectedRestaurantId } from '../restaurant-context';
import { readStoplist, fetchStoplist } from '../data-cache';

const STOPLIST_ROLES = ['manager', 'director', 'admin'];

const STOPLIST_STATUS_LABELS = {
    stop: 'Стоп',
    limit: 'Лимит',
};

const STOPLIST_STATUS_STYLES = {
    stop: 'bg-destructive/10 text-destructive',
    limit: 'bg-link/10 text-link',
};

const SECTION_LABELS = {
    kitchen: 'Кухня',
    bar: 'Бар',
};

export async function render(container, ctx) {
    const restaurants = ctx.user.restaurants ?? [];

    if (restaurants.length === 0) {
        container.innerHTML = '<p class="p-4 text-sm text-hint">Вам не назначен ресторан.</p>';

        return;
    }

    const restaurantId = getSelectedRestaurantId(restaurants);
    const showStoplist = STOPLIST_ROLES.includes(ctx.user.role);
    const showTasks = ctx.user.role === 'manager';

    if (!showStoplist && !showTasks) {
        container.innerHTML = '<p class="p-4 text-sm text-hint">Выберите раздел в меню.</p>';

        return;
    }

    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            ${showStoplist ? section('stoplist', 'Стоп-лист') : ''}
            ${showTasks ? section('tasks', 'Задачи') : ''}
        </div>
    `;

    if (showStoplist) {
        loadStoplist();
    }

    if (showTasks) {
        loadTasks();
    }

    function section(id, title) {
        return `
            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-semibold">${title}</h2>
                <div id="${id}-slider" class="flex gap-3 overflow-x-auto pb-1 snap-x snap-mandatory">
                    ${loadingHtml()}
                </div>
            </section>
        `;
    }

    async function loadStoplist() {
        // Stale-while-revalidate: paint the cached list instantly (no spinner),
        // then refresh in the background and repaint if it changed.
        const cached = readStoplist(restaurantId);

        if (cached) {
            renderStoplist(cached);
        }

        try {
            renderStoplist(await fetchStoplist(restaurantId));
        } catch (error) {
            if (!cached) {
                const slider = container.querySelector('#stoplist-slider');

                if (slider) {
                    slider.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
                }
            }
        }
    }

    function renderStoplist(data) {
        const slider = container.querySelector('#stoplist-slider');

        if (!slider) {
            return;
        }

        const entries = [
            ...(data.kitchen?.stop ?? []),
            ...(data.kitchen?.limit ?? []),
            ...(data.bar?.stop ?? []),
            ...(data.bar?.limit ?? []),
        ];

        if (entries.length === 0) {
            slider.innerHTML = '<p class="text-sm text-hint">Стоп-лист пуст.</p>';

            return;
        }

        slider.innerHTML = entries.map((entry) => `
            <a href="#/stoplist" class="flex w-56 shrink-0 flex-col gap-1 rounded-2xl bg-surface p-4 shadow-sm snap-start">
                <span class="inline-block self-start rounded-full px-2 py-0.5 text-xs font-semibold ${STOPLIST_STATUS_STYLES[entry.status] ?? ''}">
                    ${escapeHtml(STOPLIST_STATUS_LABELS[entry.status] ?? entry.status)} &middot; ${escapeHtml(SECTION_LABELS[entry.section] ?? entry.section)}
                </span>
                <p class="truncate text-sm font-medium">${escapeHtml(entry.item)}</p>
                ${entry.comment ? `<p class="truncate text-xs text-hint">${escapeHtml(entry.comment)}</p>` : ''}
            </a>
        `).join('');
    }

    async function loadTasks() {
        const slider = container.querySelector('#tasks-slider');

        try {
            const { data: tasks } = await apiFetch(`/tasks?restaurant_id=${restaurantId}&status=open`);

            if (tasks.length === 0) {
                slider.outerHTML = '<p class="text-sm text-hint">Открытых задач нет.</p>';

                return;
            }

            slider.innerHTML = tasks.map((task) => `
                <a href="#/tasks" class="flex w-56 shrink-0 flex-col gap-1 rounded-2xl bg-surface p-4 shadow-sm snap-start">
                    ${task.area ? `
                        <span class="inline-block self-start rounded-full bg-link/10 px-2 py-0.5 text-xs font-medium text-link">
                            ${escapeHtml(task.area)}
                        </span>
                    ` : ''}
                    <p class="truncate text-sm">${escapeHtml(task.description)}</p>
                    <p class="text-xs text-hint">${formatDate(task.created_at)}</p>
                </a>
            `).join('');
        } catch (error) {
            slider.outerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
        }
    }

    function formatDate(value) {
        return new Date(value).toLocaleDateString();
    }
}
