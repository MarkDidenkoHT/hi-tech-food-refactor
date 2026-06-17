import { apiFetch } from '../api';
import { escapeHtml, loadingHtml } from '../dom';
import { getSelectedRestaurantId } from '../restaurant-context';

export async function render(container, ctx) {
    const restaurants = ctx.user.restaurants ?? [];

    if (restaurants.length === 0) {
        container.innerHTML = '<p class="p-4 text-sm text-hint">Вам не назначен ресторан.</p>';

        return;
    }

    const restaurantId = getSelectedRestaurantId(restaurants);

    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-2 rounded-2xl bg-surface p-4 shadow-sm sm:flex-row">
                <input type="date" id="filter-from" class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                <input type="date" id="filter-to" class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                <input type="text" id="filter-search" placeholder="Поиск" autocomplete="off"
                    class="flex-1 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
            </div>
            <div class="flex flex-col gap-3">
                <h3 class="text-sm font-semibold">Выполненные задачи</h3>
                <div id="tasks" class="flex flex-col gap-2"></div>
            </div>
        </div>
    `;

    const tasksContainer = container.querySelector('#tasks');
    const filterFrom = container.querySelector('#filter-from');
    const filterTo = container.querySelector('#filter-to');
    const filterSearch = container.querySelector('#filter-search');

    const search = debounce(load, 300);

    filterFrom.addEventListener('change', load);
    filterTo.addEventListener('change', load);
    filterSearch.addEventListener('input', search);

    async function load() {
        tasksContainer.innerHTML = loadingHtml();

        const params = new URLSearchParams({ restaurant_id: restaurantId });

        if (filterFrom.value) {
            params.set('from', filterFrom.value);
        }

        if (filterTo.value) {
            params.set('to', filterTo.value);
        }

        if (filterSearch.value.trim()) {
            params.set('search', filterSearch.value.trim());
        }

        try {
            const data = await apiFetch(`/archive?${params.toString()}`);
            renderTasks(data.tasks);
        } catch (error) {
            tasksContainer.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
        }
    }

    function renderTasks(tasks) {
        if (tasks.length === 0) {
            tasksContainer.innerHTML = '<p class="rounded-2xl bg-surface p-4 text-sm text-hint shadow-sm">Нет выполненных задач.</p>';

            return;
        }

        tasksContainer.innerHTML = tasks.map((task) => `
            <div class="flex flex-col gap-1 rounded-2xl bg-surface p-4 shadow-sm">
                ${task.area ? `
                    <span class="inline-block w-fit rounded-full bg-link/10 px-2 py-0.5 text-xs font-medium text-link">
                        ${escapeHtml(task.area)}
                    </span>
                ` : ''}
                <p class="text-sm">${escapeHtml(task.description)}</p>
                <p class="text-xs text-hint">${escapeHtml(task.created_by ?? '—')} → ${escapeHtml(task.completed_by ?? '—')} · ${formatDate(task.completed_at)}</p>
            </div>
        `).join('');
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleString('ru-RU');
    }

    function debounce(fn, delay) {
        let timeout;

        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    }

    await load();
}
